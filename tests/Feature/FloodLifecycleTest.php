<?php
namespace Tests\Feature;
use App\Models\{Barangay, FloodTrainingRecord, FloodObservationChange, Role, Permission, User, SmsRecipient, SmsLog};
use App\Services\SmsGatewayService;
use Tests\TestCase;
class FloodLifecycleTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        // A fresh in-memory database permits real commits and after-commit alert assertions.
        $this->artisan('migrate:fresh')->assertSuccessful();
    }
    private function staff(bool $edit = true): User {
        $role = Role::create(['name'=>'Flood Analyst','slug'=>'flood-analyst','is_active'=>true]);
        foreach ($edit ? ['prediction.view','flood.edit'] : ['prediction.view'] as $slug) {
            $role->permissions()->attach(Permission::create(['name'=>$slug,'slug'=>$slug,'module'=>'flood','is_active'=>true]));
        }
        return User::factory()->create(['role_id'=>$role->id,'is_active'=>true]);
    }
    private function record(string $code = 'A'): FloodTrainingRecord {
        return FloodTrainingRecord::create(['barangay'=>'Hulo','location_name'=>'Test road','observed_at'=>now()->subHours(2),
            'flood_level_code'=>$code,'flood_status'=>'Active','risk_level'=>'Low','flood_depth_mm'=>152.4,'duration_hours'=>0,
            'month'=>now()->month,'include_in_training'=>false,'data_source'=>'Verified staff observation']);
    }
    private function payload(array $records, string $action): array {
        $expected = [];
        foreach ($records as $record) $expected[$record->id] = ['code'=>$record->flood_level_code,'status'=>$record->flood_status];
        return ['ids'=>array_map(fn($r)=>$r->id,$records),'expected'=>$expected,'action'=>$action,
            'change_observed_at'=>now()->toIso8601String(),'change_note'=>'Confirmed by field staff'];
    }
    public function test_increase_decrease_and_subside_preserve_one_incident_and_actor_history(): void {
        $user = $this->staff(); $record = $this->record();
        foreach (['increase'=>'B','decrease'=>'A','subside'=>'A'] as $action=>$code) {
            $this->actingAs($user)->postJson(route('flood-lifecycle.bulk'), $this->payload([$record->fresh()],$action))
                ->assertOk()->assertJsonPath('records.0.flood_level_code',$code);
        }
        $this->assertDatabaseCount('flood_training_records',1);
        $this->assertDatabaseCount('flood_observation_changes',4);
        $this->assertSame('Subsided',$record->fresh()->flood_status);
        $this->assertNotNull($record->fresh()->subsided_at);
        $this->assertSame($user->id,FloodObservationChange::latest('id')->first()->changed_by);
        $this->getJson(route('flood-lifecycle.history',$record))->assertOk()->assertJsonPath('changes.0.action','Subsided');
    }
    public function test_invalid_bulk_selection_rolls_back_every_incident(): void {
        $a=$this->record('A'); $d=$this->record('D');
        $this->actingAs($this->staff())->postJson(route('flood-lifecycle.bulk'),$this->payload([$a,$d],'increase'))->assertUnprocessable();
        $this->assertSame('A',$a->fresh()->flood_level_code);
        $this->assertDatabaseCount('flood_observation_changes',2);
    }
    public function test_stale_selection_and_future_observation_are_rejected(): void {
        $r=$this->record(); $payload=$this->payload([$r],'increase'); $r->update(['flood_level_code'=>'B']);
        $this->actingAs($this->staff())->postJson(route('flood-lifecycle.bulk'),$payload)->assertUnprocessable();
        $payload=$this->payload([$r->fresh()],'increase'); $payload['change_observed_at']=now()->addDay()->toIso8601String();
        $this->postJson(route('flood-lifecycle.bulk'),$payload)->assertUnprocessable();
    }
    public function test_users_without_edit_permission_cannot_change_incidents(): void {
        $r=$this->record();
        $this->actingAs($this->staff(false))->postJson(route('flood-lifecycle.bulk'),$this->payload([$r],'increase'))->assertForbidden();
    }
    public function test_changes_send_sms_once_per_event_and_subsidence_also_notifies(): void {
        $barangay=Barangay::create(['name'=>'Hulo','district'=>1,'is_active'=>true]);
        SmsRecipient::create(['full_name'=>'Recipient','phone_number'=>'09171234567','barangay_id'=>$barangay->id,
            'is_active'=>true,'receive_flood_alerts'=>true,'receive_fire_alerts'=>false]);
        $this->mock(SmsGatewayService::class, fn($mock)=>$mock->shouldReceive('send')->times(3)->andReturn(['success'=>true,'status'=>200]));
        $r=$this->record(); $user=$this->staff();
        foreach (['increase','decrease','subside'] as $action) $this->actingAs($user)->postJson(route('flood-lifecycle.bulk'),$this->payload([$r->fresh()],$action))->assertOk();
        $this->assertSame(3,SmsLog::where('status','sent')->count());
        $change=FloodObservationChange::latest('id')->first();
        app(\App\Services\FloodIncidentAlertService::class)->sendChangeAlert($r->fresh(),$change);
        $this->assertDatabaseCount('sms_logs',3);
        $this->assertSame(1,$change->notification_summary['sent']);
    }
    public function test_sms_failure_does_not_undo_a_verified_update(): void {
        $barangay=Barangay::create(['name'=>'Hulo','district'=>1,'is_active'=>true]);
        SmsRecipient::create(['full_name'=>'Recipient','phone_number'=>'09171234567','barangay_id'=>$barangay->id,'is_active'=>true,'receive_flood_alerts'=>true]);
        $this->mock(SmsGatewayService::class, fn($mock)=>$mock->shouldReceive('send')->once()->andThrow(new \RuntimeException('Gateway offline')));
        $r=$this->record();
        $this->actingAs($this->staff())->postJson(route('flood-lifecycle.bulk'),$this->payload([$r],'increase'))->assertOk();
        $this->assertSame('B',$r->fresh()->flood_level_code);
        $this->assertDatabaseHas('sms_logs',['status'=>'failed']);
    }
    public function test_failed_alert_retry_does_not_resend_successful_messages(): void {
        $barangay=Barangay::create(['name'=>'Hulo','district'=>1,'is_active'=>true]);
        SmsRecipient::create(['full_name'=>'Recipient','phone_number'=>'09171234567','barangay_id'=>$barangay->id,'is_active'=>true,'receive_flood_alerts'=>true]);
        $this->mock(SmsGatewayService::class, fn($mock)=>$mock->shouldReceive('send')->twice()->andReturn(
            ['success'=>false,'error'=>'Gateway unavailable'], ['success'=>true,'status'=>200]));
        $r=$this->record();
        $this->actingAs($this->staff())->postJson(route('flood-lifecycle.bulk'),$this->payload([$r],'increase'))->assertOk();
        $change=FloodObservationChange::latest('id')->first();
        $this->postJson(route('flood-lifecycle.retry',[$r,$change]))->assertOk();
        $this->postJson(route('flood-lifecycle.retry',[$r,$change]))->assertOk();
        $this->assertDatabaseCount('sms_logs',1);
        $this->assertDatabaseHas('sms_logs',['status'=>'sent']);
    }
    public function test_superseded_change_cannot_be_retried(): void {
        $r=$this->record(); $user=$this->staff();
        $this->actingAs($user)->postJson(route('flood-lifecycle.bulk'),$this->payload([$r],'increase'))->assertOk();
        $old=FloodObservationChange::latest('id')->first();
        $this->postJson(route('flood-lifecycle.bulk'),$this->payload([$r->fresh()],'subside'))->assertOk();
        $this->postJson(route('flood-lifecycle.retry',[$r,$old]))->assertStatus(409);
    }

}

@if($profileUser->profilePhoto()->exists())
    <img src="{{ route('profile.photo') }}" alt="Your profile picture" class="h-full w-full rounded-full object-cover">
@else
    <span>{{ mb_strtoupper(mb_substr($profileUser->name ?: 'U', 0, 1)) }}</span>
@endif

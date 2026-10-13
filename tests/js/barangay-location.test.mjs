import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import {containsPoint, normalizeBarangay} from '../../resources/js/barangay-location.js';

test('polygon interiors, holes, multipolygons, and exterior points', () => {
    const geometry = {type: 'Polygon', coordinates: [[[0,0],[4,0],[4,4],[0,4],[0,0]], [[1,1],[2,1],[2,2],[1,2],[1,1]]]};
    assert.equal(containsPoint([3,3], geometry), true);
    assert.equal(containsPoint([1.5,1.5], geometry), false);
    assert.equal(containsPoint([8,8], geometry), false);
    assert.equal(containsPoint([3,3], {type:'MultiPolygon', coordinates:[geometry.coordinates]}), true);
});

test('boundary names match database spelling variants', () => {
    for (const [a,b] of [['Hagdang Bato Itaas','Hagdan Bato Itaas'],['Wack-wack Greenhills','Wack-Wack Greenhills East'],['New Zañiga','New Zaniga'],['Pag-asa','Pag-Asa']]) {
        assert.equal(normalizeBarangay(a), normalizeBarangay(b));
    }
});

test('all 27 existing city boundaries can resolve an interior coordinate', () => {
    const data = JSON.parse(readFileSync(new URL('../../public/geojson/mandaluyong_barangays.geojson', import.meta.url)));
    assert.equal(data.features.length, 27);
    for (const feature of data.features) {
        const rings = feature.geometry.type === 'Polygon' ? feature.geometry.coordinates : feature.geometry.coordinates[0];
        const ring = rings[0];
        const xs = ring.map(point=>point[0]), ys = ring.map(point=>point[1]);
        const minX=Math.min(...xs), maxX=Math.max(...xs), minY=Math.min(...ys), maxY=Math.max(...ys);
        let found = false;
        for (let x=1;x<20&&!found;x++) for(let y=1;y<20&&!found;y++) {
            const point=[minX+(maxX-minX)*x/20,minY+(maxY-minY)*y/20];
            found=containsPoint(point,feature.geometry);
        }
        assert.equal(found,true,feature.properties.barangay);
    }
});

 test('existing overlapping boundaries are detected as ambiguous', () => {
    const data = JSON.parse(readFileSync(new URL('../../public/geojson/mandaluyong_barangays.geojson', import.meta.url)));
    const matches = data.features.filter(f=>containsPoint([121.0409247155,14.5886826875],f.geometry));
    assert.ok(matches.length > 1);
});

"""Build repeatable, complete test rows without changing the original workbook extract."""
import hashlib
import json
import random
import unicodedata
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def key(value):
    value = unicodedata.normalize("NFKD", value).encode("ascii", "ignore").decode().lower()
    return "".join(c for c in value if c.isalnum()).replace("hagdangbato", "hagdanbato")


def inside_ring(x, y, ring):
    inside = False
    for (a, b), (c, d) in zip(ring, ring[1:] + ring[:1]):
        if (b > y) != (d > y) and x < (c - a) * (y - b) / (d - b) + a:
            inside = not inside
    return inside


def inside(x, y, rings):
    return inside_ring(x, y, rings[0]) and not any(inside_ring(x, y, hole) for hole in rings[1:])


def generate():
    source = json.loads((ROOT / "database/data/fire-incident-records.json").read_text())
    geo = json.loads((ROOT / "public/geojson/mandaluyong_barangays.geojson").read_text())
    boundaries = {key(f["properties"]["barangay"]): f for f in geo["features"]}
    records = {}
    for row in source["records"]:
        incident_id = row["Incident ID"]
        barangay = row["Barangay"]
        fields = ["Latitude", "Longitude", "Severity", "Cause"]
        if key(barangay) not in boundaries:
            barangay = "New Zaniga"  # Test assignment only; original ambiguous value stays in source.
            fields.append("Barangay")
        polygon = boundaries[key(barangay)]["geometry"]["coordinates"]
        xs, ys = zip(*polygon[0])
        rng = random.Random(int(hashlib.sha256(incident_id.encode()).hexdigest(), 16))
        for _ in range(10000):
            longitude = round(rng.uniform(min(xs), max(xs)), 7)
            latitude = round(rng.uniform(min(ys), max(ys)), 7)
            if inside(longitude, latitude, polygon):
                break
        else:
            raise ValueError(f"Cannot place test point: {incident_id}")
        street = row["Street / Location"]
        if not street:
            street = f"Test Street {incident_id[-4:]}, {barangay}"
            fields.append("Street / Location")
        alarm = row["Alarm (reported)"] or row["Alarm"]
        if not row["Alarm (reported)"]:
            fields.append("Alarm")
        people, houses = row["Individuals Affected"], row["Houses Destroyed"]
        severity = ("Major" if houses >= 10 or people >= 50 or alarm == "3rd"
                    else "Moderate" if houses >= 3 or people >= 15 or alarm == "2nd" else "Minor")
        records[incident_id] = {"barangay": barangay, "location": street, "alarm_level": alarm,
                               "cause": row["Cause "], "severity": severity, "latitude": latitude,
                               "longitude": longitude, "generated_fields": fields}
    result = {"version": "fire-records-complete-test-v2", "source_sha256": source["sha256"],
              "description": "Complete test data. Added values and pins are synthetic, not verified incident facts.",
              "severity_rule": "Major: houses >=10, people >=50, or 3rd alarm; Moderate: houses >=3, people >=15, or 2nd alarm; otherwise Minor.",
              "records": records}
    (ROOT / "database/data/fire-incident-test-records.json").write_text(
        json.dumps(result, indent=2, ensure_ascii=False) + "\n")


if __name__ == "__main__":
    generate()

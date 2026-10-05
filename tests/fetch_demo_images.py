"""Download attributed sample photos once; runtime apps use Drupal-hosted files."""

import json
from pathlib import Path
import urllib.request

assets = Path(__file__).resolve().parent.parent / "assets"
assets.mkdir(exist_ok=True)
photos = [
    ("rotterdam-aerial", "Rotterdam · Erasmus Bridge", "mzrPiJ2Rnxc", "Aerial view of the Erasmus Bridge and Rotterdam waterfront"),
    ("rotterdam-sunset", "Rotterdam · Golden hour", "A61IRF1WbDQ", "Rotterdam skyline and Erasmus Bridge across the river at sunset"),
    ("rotterdam-cables", "Rotterdam · Bridge details", "Lt6yjrvAzu4", "The cables and white pylon of Rotterdam's Erasmus Bridge against a blue sky"),
    ("rotterdam-night", "Rotterdam · City lights", "x126P__Fw3A", "The Erasmus Bridge with Rotterdam buildings and evening light"),
    ("conference-audience", "Conference · The audience", "MxHjumNWlkI", "Audience seated in a conference hall during a presentation"),
    ("conference-hall", "Conference · A shared moment", "KngxPA69IKw", "Audience gathered in a large hall for a presentation"),
]
manifest = []
verified_images = {
    "mzrPiJ2Rnxc": ("photo-1690360994204-3d10cc73a08d", "Arnout van Nieuwkoop"),
    "A61IRF1WbDQ": ("photo-1761491713037-67febb9e75eb", "Alexander Psiuk"),
    "Lt6yjrvAzu4": ("photo-1559571215-0a8bfe9d480d", "micheile henderson"),
    "x126P__Fw3A": ("photo-1606331033904-0d8c8152c21d", "Roel Oosterwijk"),
    "MxHjumNWlkI": ("photo-1778877035189-60f41e9d18bf", "Ufoma Ojo"),
    "KngxPA69IKw": ("photo-1781395170484-1706591c77e7", "Mitchell Leach"),
}
for key, name, photo_id, alt in photos:
    source = f"https://unsplash.com/photos/{photo_id}"
    image_id, author = verified_images[photo_id]
    image_url = f"https://images.unsplash.com/{image_id}?fm=jpg&fit=crop&w=1000&q=82"
    credit = author + " / Unsplash"
    file_path = assets / (key + ".jpg")
    if not file_path.exists():
        image_request = urllib.request.Request(image_url, headers={"User-Agent": "Mozilla/5.0"})
        file_path.write_bytes(urllib.request.urlopen(image_request, timeout=30).read())
    manifest.append({"key": key, "name": name, "alt": alt, "credit": credit, "source": source, "file": file_path.name})
    print(f"Downloaded {key}: {file_path.stat().st_size} bytes")
(assets / "manifest.json").write_text(json.dumps(manifest, indent=2))

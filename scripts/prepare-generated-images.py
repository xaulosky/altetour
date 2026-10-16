"""Copy generated originals into the project and encode responsive WebP assets."""
import json
import shutil
from pathlib import Path
from PIL import Image

root = Path(__file__).resolve().parents[1]
manifest = json.loads((root / 'scripts/generated-images.json').read_text(encoding='utf-8'))
extra_manifest = root / 'scripts/generated-images-extra.json'
if extra_manifest.exists():
    manifest['images'].extend(json.loads(extra_manifest.read_text(encoding='utf-8'))['images'])
originals = root / 'design/generated-images'
assets = root / 'frontend/public/assets'
originals.mkdir(parents=True, exist_ok=True)
names = {'istanbul': 'estambul', 'egeo': 'izmir'}
for entry in manifest['images']:
    key = entry['key']
    original = originals / f'{key}.png'
    if not original.exists():
        shutil.copy2(entry['source'], original)
    image = Image.open(original).convert('RGB')
    name = names.get(key, key)
    image.save(assets / f'{name}.webp', 'WEBP', quality=90, method=6)
    for width in (640, 960):
        scaled = image.resize((width, round(image.height * width / image.width)), Image.Resampling.LANCZOS)
        scaled.save(assets / f'{name}-{width}.webp', 'WEBP', quality=86, method=6)
    print(f'{name}: {image.width}x{image.height}, {(assets / (name + ".webp")).stat().st_size // 1024} KB')

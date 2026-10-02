import os
import json
import time
import subprocess
import urllib.request
import urllib.parse
from concurrent.futures import ThreadPoolExecutor

LANG_MAP = {
    'es': 'es',
    'fr': 'fr',
    'de': 'de',
    'ar': 'ar',
    'hi': 'hi',
    'pt': 'pt',
    'it': 'it',
    'zh': 'zh-CN',
    'ja': 'ja',
    'ru': 'ru',
    'id': 'id',
    'tr': 'tr',
    'nl': 'nl'
}

EXPLICIT_ES = {
    "Monthly Billing": "Facturación mensual",
    "Annual Billing": "Facturación anual",
    "Unlimited Invoices": "Facturas ilimitadas",
    "Unlimited Products": "Productos ilimitados",
    "Unlimited POS Devices": "Dispositivos POS ilimitados",
    "Unlimited Staff": "Personal ilimitado",
    "Simple, transparent pricing for every tier": "Precios simples y transparentes para cada nivel",
    "Launch in minutes with zero setup fees. Choose monthly or annual billing.": "Lanza en minutos sin tarifas de configuración. Elige facturación mensual o anual.",
    "Predictable Investment": "Inversión Predecible",
    "Save 20%": "Ahorra 20%",
    "Invoices/mo": "Facturas/mes",
    "Products": "Productos",
    "Devices": "Dispositivos",
    "Staff": "Personal",
    "MOST POPULAR": "MÁS POPULAR",
    "Start Free Trial": "Comenzar prueba gratuita",
    "Select Plan": "Seleccionar plan"
}

def translate_single(text, target_code, retries=3):
    if not text or not text.strip():
        return text
    # If purely symbols, numbers, urls, or emails, return as is
    if all(c in '0123456789+-.,/\\%$#@!&*() \t\n\r' for c in text):
        return text
    if '@' in text and '.' in text and not ' ' in text:
        return text

    url = f'https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl={target_code}&dt=t&q=' + urllib.parse.quote(text)
    for attempt in range(retries):
        try:
            req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
            with urllib.request.urlopen(req, timeout=12) as res:
                data = json.loads(res.read().decode('utf-8'))
                translated = ''.join([part[0] for part in data[0] if part[0]])
                return translated.strip() if translated else text
        except Exception as e:
            if attempt < retries - 1:
                time.sleep(0.8 * (attempt + 1))
            else:
                return text

def translate_batch(batch, target_code):
    if len(batch) == 1:
        return [translate_single(batch[0], target_code)]
    
    delimiter = '\n|||\n'
    text = delimiter.join(batch)
    url = f'https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl={target_code}&dt=t&q=' + urllib.parse.quote(text)
    try:
        req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
        with urllib.request.urlopen(req, timeout=15) as res:
            data = json.loads(res.read().decode('utf-8'))
            translated_full = ''.join([part[0] for part in data[0] if part[0]])
            parts = [p.strip() for p in translated_full.split('|||')]
            if len(parts) == len(batch):
                return parts
            else:
                return [translate_single(item, target_code) for item in batch]
    except Exception:
        return [translate_single(item, target_code) for item in batch]

def process_language(loc, target_code, en_keys):
    file_path = f'lang/{loc}.json'
    
    # For Spanish, recover the authentic base from git HEAD
    if loc == 'es':
        try:
            raw_head = subprocess.check_output(['git', 'show', 'HEAD:lang/es.json'])
            current_data = json.loads(raw_head)
            print(f"[ES] Restored {len(current_data)} base translations from git HEAD.")
        except Exception as e:
            print(f"[ES] Could not read from git HEAD: {e}")
            with open(file_path, 'r', encoding='utf-8') as f:
                current_data = json.load(f)
    else:
        with open(file_path, 'r', encoding='utf-8') as f:
            current_data = json.load(f)

    # Apply explicit overrides if any
    if loc == 'es':
        for k, v in EXPLICIT_ES.items():
            current_data[k] = v

    # Identify missing or untranslated keys
    missing_keys = []
    for k in en_keys:
        if k not in current_data:
            missing_keys.append(k)
        elif loc == 'es' and current_data[k] == k and len(k) > 3 and not all(c in '0123456789+-.,/\\%$#@!&*() ' for c in k):
            missing_keys.append(k)

    print(f"[{loc.upper()}] {len(missing_keys)} keys to translate...")
    
    if missing_keys:
        # Separate multi-line vs single-line
        multi_line = [k for k in missing_keys if '\n' in k]
        single_line = [k for k in missing_keys if '\n' not in k]

        for k in multi_line:
            current_data[k] = translate_single(k, target_code)
            time.sleep(0.04)

        chunk_size = 12
        for i in range(0, len(single_line), chunk_size):
            chunk = single_line[i:i + chunk_size]
            res = translate_batch(chunk, target_code)
            for orig, trans in zip(chunk, res):
                current_data[orig] = trans
            time.sleep(0.08)

    # Re-verify explicit overrides
    if loc == 'es':
        for k, v in EXPLICIT_ES.items():
            current_data[k] = v

    # Save to disk
    with open(file_path, 'w', encoding='utf-8') as f:
        json.dump(current_data, f, indent=4, ensure_ascii=False)

    print(f"[{loc.upper()}] Done! Total keys: {len(current_data)}.")

def main():
    with open('lang/en.json', 'r', encoding='utf-8') as f:
        en_data = json.load(f)
    en_keys = list(en_data.keys())
    print(f"English keys count: {len(en_keys)}")

    # Run in parallel
    with ThreadPoolExecutor(max_workers=4) as executor:
        futures = [
            executor.submit(process_language, loc, code, en_keys)
            for loc, code in LANG_MAP.items()
        ]
        for f in futures:
            f.result()

    print("All language catalogs completed!")

if __name__ == '__main__':
    main()

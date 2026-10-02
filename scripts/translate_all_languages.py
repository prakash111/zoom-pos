import os
import json
import time
import urllib.request
import urllib.parse
from concurrent.futures import ThreadPoolExecutor

TARGET_LANGS = {
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

def translate_single(text, target_code, retries=3):
    if not text.strip():
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
                time.sleep(1.0 * (attempt + 1))
            else:
                print(f"Warning: Failed to translate single '{text[:30]}...' to {target_code}: {e}")
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
                # Count mismatch, fallback to single translation for this batch
                return [translate_single(item, target_code) for item in batch]
    except Exception as e:
        return [translate_single(item, target_code) for item in batch]

def process_language(loc, target_code, keys):
    file_path = f'lang/{loc}.json'
    if not os.path.exists(file_path):
        print(f"File {file_path} does not exist, creating new dictionary.")
        current_data = {}
    else:
        with open(file_path, 'r', encoding='utf-8') as f:
            try:
                current_data = json.load(f)
            except Exception:
                current_data = {}

    missing_keys = [k for k in keys if k not in current_data or current_data[k] == k]
    print(f"[{loc.upper()}] Starting: {len(missing_keys)} missing / untranslated keys...")

    # Separate multi-line vs single-line
    multi_line = [k for k in missing_keys if '\n' in k]
    single_line = [k for k in missing_keys if '\n' not in k]

    translations = {}

    # Translate multi-line keys individually
    for k in multi_line:
        translations[k] = translate_single(k, target_code)
        time.sleep(0.05)

    # Translate single-line keys in batches of 10
    chunk_size = 10
    for i in range(0, len(single_line), chunk_size):
        chunk = single_line[i:i + chunk_size]
        res = translate_batch(chunk, target_code)
        for orig, trans in zip(chunk, res):
            translations[orig] = trans
        time.sleep(0.1)

    # Merge translations into current_data
    for k, v in translations.items():
        current_data[k] = v

    # Write back sorted dictionary
    with open(file_path, 'w', encoding='utf-8') as f:
        json.dump(current_data, f, indent=4, ensure_ascii=False)

    print(f"[{loc.upper()}] Finished: Total {len(current_data)} keys saved to {file_path}.")

def main():
    keys_file = '/tmp/final_landing_keys.json'
    with open(keys_file, 'r', encoding='utf-8') as f:
        keys = json.load(f)
    print(f"Total keys to ensure across all languages: {len(keys)}")

    # 1. Update English lang/en.json
    with open('lang/en.json', 'r', encoding='utf-8') as f:
        en_data = json.load(f)
    added_en = 0
    for k in keys:
        if k not in en_data:
            en_data[k] = k
            added_en += 1
    with open('lang/en.json', 'w', encoding='utf-8') as f:
        json.dump(en_data, f, indent=4, ensure_ascii=False)
    print(f"[EN] Added {added_en} new keys. Total keys: {len(en_data)}.")

    # 2. Process all target languages in parallel threads
    print(f"Translating for {len(TARGET_LANGS)} languages concurrently...")
    with ThreadPoolExecutor(max_workers=4) as executor:
        futures = [
            executor.submit(process_language, loc, code, keys)
            for loc, code in TARGET_LANGS.items()
        ]
        for future in futures:
            future.result()

    print("All language files successfully updated!")

if __name__ == '__main__':
    main()

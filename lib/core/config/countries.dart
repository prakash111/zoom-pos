import 'tax_jurisdictions.dart';

/// ISO 3166-1 alpha-2 → English short name for every sovereign country plus
/// the commonly-used territories. Drives the Store Profile country picker.
///
/// This is deliberately the *full* list — the [kTaxJurisdictions] subset only
/// governs which of these get one-tap default tax rules, and any code here (or
/// a hand-typed one) still works for everything else.
const Map<String, String> kCountries = {
  'AF': 'Afghanistan',
  'AX': 'Åland Islands',
  'AL': 'Albania',
  'DZ': 'Algeria',
  'AS': 'American Samoa',
  'AD': 'Andorra',
  'AO': 'Angola',
  'AI': 'Anguilla',
  'AG': 'Antigua and Barbuda',
  'AR': 'Argentina',
  'AM': 'Armenia',
  'AW': 'Aruba',
  'AU': 'Australia',
  'AT': 'Austria',
  'AZ': 'Azerbaijan',
  'BS': 'Bahamas',
  'BH': 'Bahrain',
  'BD': 'Bangladesh',
  'BB': 'Barbados',
  'BY': 'Belarus',
  'BE': 'Belgium',
  'BZ': 'Belize',
  'BJ': 'Benin',
  'BM': 'Bermuda',
  'BT': 'Bhutan',
  'BO': 'Bolivia',
  'BA': 'Bosnia and Herzegovina',
  'BW': 'Botswana',
  'BR': 'Brazil',
  'IO': 'British Indian Ocean Territory',
  'BN': 'Brunei Darussalam',
  'BG': 'Bulgaria',
  'BF': 'Burkina Faso',
  'BI': 'Burundi',
  'CV': 'Cabo Verde',
  'KH': 'Cambodia',
  'CM': 'Cameroon',
  'CA': 'Canada',
  'KY': 'Cayman Islands',
  'CF': 'Central African Republic',
  'TD': 'Chad',
  'CL': 'Chile',
  'CN': 'China',
  'CO': 'Colombia',
  'KM': 'Comoros',
  'CG': 'Congo',
  'CD': 'Congo (Democratic Republic)',
  'CK': 'Cook Islands',
  'CR': 'Costa Rica',
  'CI': "Côte d'Ivoire",
  'HR': 'Croatia',
  'CU': 'Cuba',
  'CW': 'Curaçao',
  'CY': 'Cyprus',
  'CZ': 'Czechia',
  'DK': 'Denmark',
  'DJ': 'Djibouti',
  'DM': 'Dominica',
  'DO': 'Dominican Republic',
  'EC': 'Ecuador',
  'EG': 'Egypt',
  'SV': 'El Salvador',
  'GQ': 'Equatorial Guinea',
  'ER': 'Eritrea',
  'EE': 'Estonia',
  'SZ': 'Eswatini',
  'ET': 'Ethiopia',
  'FK': 'Falkland Islands',
  'FO': 'Faroe Islands',
  'FJ': 'Fiji',
  'FI': 'Finland',
  'FR': 'France',
  'GF': 'French Guiana',
  'PF': 'French Polynesia',
  'GA': 'Gabon',
  'GM': 'Gambia',
  'GE': 'Georgia',
  'DE': 'Germany',
  'GH': 'Ghana',
  'GI': 'Gibraltar',
  'GR': 'Greece',
  'GL': 'Greenland',
  'GD': 'Grenada',
  'GP': 'Guadeloupe',
  'GU': 'Guam',
  'GT': 'Guatemala',
  'GG': 'Guernsey',
  'GN': 'Guinea',
  'GW': 'Guinea-Bissau',
  'GY': 'Guyana',
  'HT': 'Haiti',
  'HN': 'Honduras',
  'HK': 'Hong Kong',
  'HU': 'Hungary',
  'IS': 'Iceland',
  'IN': 'India',
  'ID': 'Indonesia',
  'IR': 'Iran',
  'IQ': 'Iraq',
  'IE': 'Ireland',
  'IM': 'Isle of Man',
  'IL': 'Israel',
  'IT': 'Italy',
  'JM': 'Jamaica',
  'JP': 'Japan',
  'JE': 'Jersey',
  'JO': 'Jordan',
  'KZ': 'Kazakhstan',
  'KE': 'Kenya',
  'KI': 'Kiribati',
  'KW': 'Kuwait',
  'KG': 'Kyrgyzstan',
  'LA': 'Laos',
  'LV': 'Latvia',
  'LB': 'Lebanon',
  'LS': 'Lesotho',
  'LR': 'Liberia',
  'LY': 'Libya',
  'LI': 'Liechtenstein',
  'LT': 'Lithuania',
  'LU': 'Luxembourg',
  'MO': 'Macao',
  'MG': 'Madagascar',
  'MW': 'Malawi',
  'MY': 'Malaysia',
  'MV': 'Maldives',
  'ML': 'Mali',
  'MT': 'Malta',
  'MH': 'Marshall Islands',
  'MQ': 'Martinique',
  'MR': 'Mauritania',
  'MU': 'Mauritius',
  'YT': 'Mayotte',
  'MX': 'Mexico',
  'FM': 'Micronesia',
  'MD': 'Moldova',
  'MC': 'Monaco',
  'MN': 'Mongolia',
  'ME': 'Montenegro',
  'MS': 'Montserrat',
  'MA': 'Morocco',
  'MZ': 'Mozambique',
  'MM': 'Myanmar',
  'NA': 'Namibia',
  'NR': 'Nauru',
  'NP': 'Nepal',
  'NL': 'Netherlands',
  'NC': 'New Caledonia',
  'NZ': 'New Zealand',
  'NI': 'Nicaragua',
  'NE': 'Niger',
  'NG': 'Nigeria',
  'NU': 'Niue',
  'NF': 'Norfolk Island',
  'KP': 'North Korea',
  'MK': 'North Macedonia',
  'MP': 'Northern Mariana Islands',
  'NO': 'Norway',
  'OM': 'Oman',
  'PK': 'Pakistan',
  'PW': 'Palau',
  'PS': 'Palestine',
  'PA': 'Panama',
  'PG': 'Papua New Guinea',
  'PY': 'Paraguay',
  'PE': 'Peru',
  'PH': 'Philippines',
  'PL': 'Poland',
  'PT': 'Portugal',
  'PR': 'Puerto Rico',
  'QA': 'Qatar',
  'RE': 'Réunion',
  'RO': 'Romania',
  'RU': 'Russia',
  'RW': 'Rwanda',
  'BL': 'Saint Barthélemy',
  'KN': 'Saint Kitts and Nevis',
  'LC': 'Saint Lucia',
  'MF': 'Saint Martin',
  'VC': 'Saint Vincent and the Grenadines',
  'WS': 'Samoa',
  'SM': 'San Marino',
  'ST': 'Sao Tome and Principe',
  'SA': 'Saudi Arabia',
  'SN': 'Senegal',
  'RS': 'Serbia',
  'SC': 'Seychelles',
  'SL': 'Sierra Leone',
  'SG': 'Singapore',
  'SX': 'Sint Maarten',
  'SK': 'Slovakia',
  'SI': 'Slovenia',
  'SB': 'Solomon Islands',
  'SO': 'Somalia',
  'ZA': 'South Africa',
  'KR': 'South Korea',
  'SS': 'South Sudan',
  'ES': 'Spain',
  'LK': 'Sri Lanka',
  'SD': 'Sudan',
  'SR': 'Suriname',
  'SE': 'Sweden',
  'CH': 'Switzerland',
  'SY': 'Syria',
  'TW': 'Taiwan',
  'TJ': 'Tajikistan',
  'TZ': 'Tanzania',
  'TH': 'Thailand',
  'TL': 'Timor-Leste',
  'TG': 'Togo',
  'TK': 'Tokelau',
  'TO': 'Tonga',
  'TT': 'Trinidad and Tobago',
  'TN': 'Tunisia',
  'TR': 'Türkiye',
  'TM': 'Turkmenistan',
  'TC': 'Turks and Caicos Islands',
  'TV': 'Tuvalu',
  'UG': 'Uganda',
  'UA': 'Ukraine',
  'AE': 'United Arab Emirates',
  'GB': 'United Kingdom',
  'US': 'United States',
  'UY': 'Uruguay',
  'UZ': 'Uzbekistan',
  'VU': 'Vanuatu',
  'VA': 'Vatican City',
  'VE': 'Venezuela',
  'VN': 'Vietnam',
  'VG': 'Virgin Islands (British)',
  'VI': 'Virgin Islands (U.S.)',
  'YE': 'Yemen',
  'ZM': 'Zambia',
  'ZW': 'Zimbabwe',
};

/// The full option set for the Store Profile country dropdown: every country
/// in [kCountries] plus any non-standard [kTaxJurisdictions] entry that isn't a
/// real ISO country (e.g. `EU` — European Union), so existing selections are
/// never dropped. Sorted by display name.
List<MapEntry<String, String>> countryPickerOptions() {
  final merged = <String, String>{...kCountries};
  for (final entry in kTaxJurisdictions.entries) {
    merged.putIfAbsent(entry.key, () => entry.value);
  }
  final list = merged.entries.toList()
    ..sort((a, b) => a.value.toLowerCase().compareTo(b.value.toLowerCase()));
  return list;
}

/// Resolves a stored country value — which may be an ISO2 code (`BR`), a full
/// name (`Brazil`), or a 3-letter/other variant — to a canonical picker key,
/// or `null` when it isn't recognised (caller then uses the "Other" path).
String? resolveCountryCode(String? raw) {
  final value = raw?.trim();
  if (value == null || value.isEmpty) return null;

  final upper = value.toUpperCase();
  if (kCountries.containsKey(upper)) return upper;
  if (kTaxJurisdictions.containsKey(upper)) return upper;

  final lower = value.toLowerCase();
  for (final entry in kCountries.entries) {
    if (entry.value.toLowerCase() == lower) return entry.key;
  }
  for (final entry in kTaxJurisdictions.entries) {
    if (entry.value.toLowerCase() == lower) return entry.key;
  }
  return null;
}

/// ISO 3166-1 alpha-2 → international phone dialing code.
const Map<String, String> kCountryDialCodes = {
  'IN': '+91',
  'US': '+1',
  'CA': '+1',
  'GB': '+44',
  'AE': '+971',
  'SA': '+966',
  'AU': '+61',
  'NZ': '+64',
  'SG': '+65',
  'MY': '+60',
  'ID': '+62',
  'PH': '+63',
  'TH': '+66',
  'VN': '+84',
  'PK': '+92',
  'BD': '+880',
  'LK': '+94',
  'NP': '+977',
  'DE': '+49',
  'FR': '+33',
  'IT': '+39',
  'ES': '+34',
  'PT': '+351',
  'NL': '+31',
  'BE': '+32',
  'CH': '+41',
  'AT': '+43',
  'SE': '+46',
  'NO': '+47',
  'DK': '+45',
  'FI': '+358',
  'PL': '+48',
  'IE': '+353',
  'RU': '+7',
  'KZ': '+7',
  'TR': '+90',
  'ZA': '+27',
  'EG': '+20',
  'NG': '+234',
  'KE': '+254',
  'GH': '+233',
  'BR': '+55',
  'MX': '+52',
  'AR': '+54',
  'CO': '+57',
  'CL': '+56',
  'PE': '+51',
  'QA': '+974',
  'KW': '+965',
  'BH': '+973',
  'OM': '+968',
  'JO': '+962',
  'LB': '+961',
  'IL': '+972',
  'HK': '+852',
  'TW': '+886',
  'KR': '+82',
  'JP': '+81',
  'CN': '+86',
  'AF': '+93',
  'AL': '+355',
  'DZ': '+213',
  'AD': '+376',
  'AO': '+244',
  'AM': '+374',
  'AZ': '+994',
  'BY': '+375',
  'BZ': '+501',
  'BJ': '+229',
  'BT': '+975',
  'BO': '+591',
  'BA': '+387',
  'BW': '+267',
  'BN': '+673',
  'BG': '+359',
  'BF': '+226',
  'BI': '+257',
  'KH': '+855',
  'CM': '+237',
  'CR': '+506',
  'HR': '+385',
  'CY': '+357',
  'CZ': '+420',
  'EC': '+593',
  'EE': '+372',
  'ET': '+251',
  'GE': '+995',
  'GT': '+502',
  'HU': '+36',
  'IS': '+354',
  'IQ': '+964',
  'JM': '+1',
  'KG': '+996',
  'LV': '+371',
  'LY': '+218',
  'LT': '+370',
  'LU': '+352',
  'MV': '+960',
  'MU': '+230',
  'MD': '+373',
  'MC': '+377',
  'MA': '+212',
  'PA': '+507',
  'PY': '+595',
  'RO': '+40',
  'RW': '+250',
  'SN': '+221',
  'RS': '+381',
  'SK': '+421',
  'SI': '+386',
  'TZ': '+255',
  'TN': '+216',
  'UG': '+256',
  'UA': '+380',
  'UY': '+598',
  'UZ': '+998',
  'VE': '+58',
  'YE': '+967',
  'ZM': '+260',
  'ZW': '+263',
};

/// Resolves dial code for country ISO code, falling back to [fallback].
String dialCodeForCountry(String? countryIso, {String fallback = '+91'}) {
  if (countryIso == null || countryIso.trim().isEmpty) return fallback;
  final upper = countryIso.trim().toUpperCase();
  return kCountryDialCodes[upper] ?? fallback;
}

/// Resolves ISO country code for a given dial code, falling back to [fallback].
String countryForDialCode(String? dialCode, {String fallback = 'IN'}) {
  if (dialCode == null || dialCode.trim().isEmpty) return fallback;
  var clean = dialCode.trim();
  if (!clean.startsWith('+')) clean = '+$clean';
  for (final entry in kCountryDialCodes.entries) {
    if (entry.value == clean) return entry.key;
  }
  return fallback;
}

/// Normalizes a phone number to standard international E.164.
/// E.g. '9876543210' -> '+919876543210'
///      '09876543210' -> '+919876543210'
///      '+91 98765 43210' -> '+919876543210'
String normalizePhoneNumber(String? phone, {String defaultDialCode = '+91'}) {
  if (phone == null || phone.trim().isEmpty) return '';
  var raw = phone.trim();
  var defaultDial = defaultDialCode.trim();
  if (!defaultDial.startsWith('+')) defaultDial = '+$defaultDial';

  if (raw.startsWith('00')) {
    raw = '+${raw.substring(2)}';
  }

  if (raw.startsWith('+')) {
    final digits = raw.substring(1).replaceAll(RegExp(r'\D+'), '');
    return digits.isNotEmpty ? '+$digits' : '';
  }

  var digits = raw.replaceAll(RegExp(r'\D+'), '');
  if (digits.isEmpty) return '';

  // Strip leading trunk zeros (e.g. 09876543210 -> 9876543210)
  while (digits.startsWith('0') && digits.length > 1) {
    digits = digits.substring(1);
  }

  final dialDigits = defaultDial.replaceFirst('+', '');
  if (digits.startsWith(dialDigits) && digits.length >= dialDigits.length + 7) {
    return '+$digits';
  }

  return '$defaultDial$digits';
}

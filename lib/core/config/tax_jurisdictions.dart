/// Country codes the backend has built-in GST/VAT/sales-tax presets for
/// (`TaxCalculationService::JURISDICTIONS`), for the Settings country picker.
/// Any other ISO2 code is still supported via the "Other" fallback — this
/// list only drives which countries get one-tap default tax rules.
const Map<String, String> kTaxJurisdictions = {
  'IN': 'India',
  'US': 'United States',
  'GB': 'United Kingdom',
  'AE': 'United Arab Emirates',
  'SA': 'Saudi Arabia',
  'CA': 'Canada',
  'AU': 'Australia',
  'EU': 'European Union',
  'SG': 'Singapore',
  'BR': 'Brazil',
  'MX': 'Mexico',
};

const String kOtherCountrySentinel = 'other';

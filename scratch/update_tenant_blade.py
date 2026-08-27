import re

with open('resources/views/layouts/tenant.blade.php', 'r') as f:
    text = f.read()

replacements = [
    # Expanded items
    ('href="{{ route(\'tenant.restaurant.pos\') }}"', 'x-show="isItemVisible(\'restaurant_pos\')" href="{{ route(\'tenant.restaurant.pos\') }}"'),
    ('href="{{ route(\'tenant.restaurant.tables\') }}"', 'x-show="isItemVisible(\'tables\')" href="{{ route(\'tenant.restaurant.tables\') }}"'),
    ('href="{{ route(\'tenant.restaurant.kds\') }}"', 'x-show="isItemVisible(\'kds\')" href="{{ route(\'tenant.restaurant.kds\') }}"'),
    ('href="{{ route(\'tenant.sales.create\') }}"', 'x-show="isItemVisible(\'pos\')" href="{{ route(\'tenant.sales.create\') }}"'),
    ('href="{{ route(\'tenant.sales.index\') }}"', 'x-show="isItemVisible(\'sales\')" href="{{ route(\'tenant.sales.index\') }}"'),
    ('href="{{ route(\'tenant.quotes.index\') }}"', 'x-show="isItemVisible(\'quotes\')" href="{{ route(\'tenant.quotes.index\') }}"'),
    ('href="{{ route(\'tenant.customers.index\') }}"', 'x-show="isItemVisible(\'customers\')" href="{{ route(\'tenant.customers.index\') }}"'),
    ('href="{{ route(\'tenant.financials.cash_register\') }}"', 'x-show="isItemVisible(\'register\')" href="{{ route(\'tenant.financials.cash_register\') }}"'),
    ('href="{{ route(\'tenant.financials.receivables\') }}"', 'x-show="isItemVisible(\'receivables\')" href="{{ route(\'tenant.financials.receivables\') }}"'),
    ('href="{{ route(\'tenant.financials.payables\') }}"', 'x-show="isItemVisible(\'receivables\')" href="{{ route(\'tenant.financials.payables\') }}"'),
    ('href="{{ route(\'tenant.reports.index\') }}"', 'x-show="isItemVisible(\'reports\')" href="{{ route(\'tenant.reports.index\') }}"'),
    ('href="{{ route(\'tenant.products.index\') }}"', 'x-show="isItemVisible(\'products\')" href="{{ route(\'tenant.products.index\') }}"'),
    ('href="{{ route(\'tenant.categories.index\') }}"', 'x-show="isItemVisible(\'categories\')" href="{{ route(\'tenant.categories.index\') }}"'),
    ('href="{{ route(\'tenant.catalog.index\') }}"', 'x-show="isItemVisible(\'products\')" href="{{ route(\'tenant.catalog.index\') }}"'),
    ('href="{{ route(\'tenant.settings.index\') }}"', 'x-show="isItemVisible(\'settings\')" href="{{ route(\'tenant.settings.index\') }}"'),
    ('href="{{ route(\'tenant.billing.index\') }}"', 'x-show="isItemVisible(\'billing\')" href="{{ route(\'tenant.billing.index\') }}"'),
]

# Ensure we don't double up x-show if already present
for search_str, repl_str in replacements:
    # Match any <a> tags containing search_str where x-show is not already right before it
    # We can do exact string replacements and clean duplicate x-shows
    text = text.replace(search_str, repl_str)

# Clean up any duplicated `x-show="isItemVisible('...')"` on the same tag
text = re.sub(r'x-show="isItemVisible\(\'([^\']+)\'\)"\s+x-show="isItemVisible\(\'\1\'\)"', r'x-show="isItemVisible(\'\1\')"', text)

with open('resources/views/layouts/tenant.blade.php', 'w') as f:
    f.write(text)

print('Updated tenant.blade.php navigation visibility bindings successfully.')

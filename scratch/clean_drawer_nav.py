with open('resources/views/layouts/tenant.blade.php', 'r') as f:
    text = f.read()

# Locate drawer start and end
drawer_marker = '<!-- Structured Toggle Menu List Items (Strictly Mode Isolated) -->'
drawer_end_marker = '</nav>'

parts = text.split(drawer_marker)
if len(parts) == 2:
    before = parts[0] + drawer_marker
    after_parts = parts[1].split(drawer_end_marker, 1)
    drawer_content = after_parts[0]
    rest = drawer_end_marker + after_parts[1]
    
    # Remove x-show="isItemVisible(...)" from drawer content
    import re
    cleaned_drawer = re.sub(r'\s*x-show="isItemVisible\([^)]+\)"', '', drawer_content)
    
    with open('resources/views/layouts/tenant.blade.php', 'w') as f:
        f.write(before + cleaned_drawer + rest)
    print('Cleaned drawer navigation items successfully.')
else:
    print('Drawer marker not found.')

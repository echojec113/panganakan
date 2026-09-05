#!/usr/bin/env python3
with open('resources/views/dashboards/staff.blade.php', 'rb') as f:
    content = f.read(2000)
has_brace_open = chr(123) in content
has_brace_close = chr(125) in content
print(f'Contains {{: {has_brace_open}')
print(f'Contains }}: {has_brace_close}')
"
#!/usr/bin/env python3
with open('resources/views/dashboards/staff.blade.php', 'r', encoding='utf-8', errors='replace') as f:
    content = f.read()

results = []
# Main wrapper
mw = '<div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-7 space-y-6">' in content
results.append(('Main wrapper', 'YES' if mw else 'NO'))

# KPI grid
kg = 'grid grid-cols-2 lg:grid-cols-4 gap-4' in content
results.append(('KPI grid original', 'YES' if kg else 'NO'))

# ROW 2
r2 = 'ROW 2' in content
results.append(('ROW 2 present', 'YES' if r2 else 'NO'))

# ROW 3
r3 = 'ROW 3' in content
results.append(('ROW 3 present', 'YES' if r3 else 'NO'))

# Pri width
pw = 'lg:col-span-3' in content or 'lg:col-span-2' in content
results.append(('Pri width classes', 'YES' if pw else 'NO'))

# Clinical group
cg = 'clinical-group' in content
results.append(('Clinical group', 'YES' if cg else 'NO'))

# Priority Alerts
pa = 'Priority Alerts' in content
results.append(('Priority Alerts', 'YES' if pa else 'NO'))

# Upcoming Appointments
ua = 'Upcoming Appointments' in content
results.append(('Upcoming Appointments', 'YES' if ua else 'NO'))

# Follow-Up Schedule
fus = 'Follow-Up Schedule' in content
results.append(('Follow-Up Schedule', 'YES' if fus else 'NO'))

# Recent Visits
rv = 'Recent Visits' in content
results.append(('Recent Visits', 'YES' if rv else 'NO'))

# New Patient link
np = 'patients.create' in content
results.append(('New Patient link', 'YES' if np else 'NO'))

# Record Visit link
rvl = 'prenatal-visits.create' in content
results.append(('Record Visit link', 'YES' if rvl else 'NO'))

print('Restored structure checks:')
for result, desc in results:
    print(f'  {result}: {desc}')
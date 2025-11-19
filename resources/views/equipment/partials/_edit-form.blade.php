{{-- This file now simply includes the updated _form partial --}}
{{-- It passes the necessary variables for editing --}}
{{-- ✅✅✅ Pass defaultDeptKey HERE ✅✅✅ --}}
@include('equipment.partials._form', [
    'equipment' => $equipment,       // The existing equipment model instance
    'categories' => $categories,     // Collection of categories
    'locations' => $locations,       // Collection of locations
    'units' => $units,               // Collection of units
    'defaultDeptKey' => $defaultDeptKey // <-- Added this line
])


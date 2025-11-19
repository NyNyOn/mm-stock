{{-- resources/views/equipment/create.blade.php --}}
<form id="create-equipment-form-new"
      class="space-y-6 create-equipment-form-instance"
      method="POST"
      action="{{ route('equipment.store') }}"
      enctype="multipart/form-data">
    @csrf

    @include('equipment.partials._form')

</form>

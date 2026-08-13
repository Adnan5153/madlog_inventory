@props(['name', 'value' => null])

<input type="number" name="{{ $name }}" id="{{ $name }}"
       value="{{ old($name, $value) }}"
       class="form-control @error($name) is-invalid @enderror"
       step="1">
@error($name) <div class="invalid-feedback">{{ $message }}</div> @enderror
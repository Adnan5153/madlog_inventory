@props(['name', 'value' => null])

<input type="text" name="{{ $name }}" id="{{ $name }}"
       value="{{ old($name, $value) }}"
       class="form-control @error($name) is-invalid @enderror"
       maxlength="500">
@error($name) <div class="invalid-feedback">{{ $message }}</div> @enderror
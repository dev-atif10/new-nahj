<!--<div class="form-group">-->
<!--    <label class="input-label" for="email">{{ trans('auth.email') }} {{ !empty($optional) ? "(". trans('public.optional') .")" : '' }}:</label>-->
<!--    <input name="email" type="text" class="form-control @error('email') is-invalid @enderror"-->
<!--           value="{{ old('email') }}" id="email" aria-describedby="emailHelp">-->

<!--    @error('email')-->
<!--    <div class="invalid-feedback">-->
<!--        {{ $message }}-->
<!--    </div>-->
<!--    @enderror-->
<!--</div>-->


<div class="form-group">
    <label class="input-label" for="email">{{ trans('auth.email') }} {{ !empty($optional) ? "(". trans('public.optional') .")" : '' }}:</label>

    <input name="email" type="text" 
           class="form-control @error('email') is-invalid @enderror"
           value="{{ old('email') }}" 
           id="email" 
           placeholder="example@domain.com"
           @if(empty($optional)) required @endif>

    @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>


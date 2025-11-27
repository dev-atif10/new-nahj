<section class="mt-30">
    <h2 class="section-title after-line">{{ trans('site.identity_and_financial') }}</h2>
    <div class="mt-15">
        @if($user->financial_approval)
            <p class="font-14 text-primary">{{ trans('site.identity_and_financial_verified') }}</p>
        @else
            <p class="font-14 text-danger">{{ trans('site.identity_and_financial_not_verified') }}</p>
        @endif
    </div>

    <div class="row mt-20">
        <div class="col-12 col-lg-4">


            <div class="js-bank-specifications-card">
                @if(!empty($user) and !empty($user->selectedBank) and !empty($user->selectedBank->bank))
                    @foreach($user->selectedBank->bank->specifications as $specification)
                        @php
                            $selectedBankSpecification = $user->selectedBank->specifications->where('user_selected_bank_id', $user->selectedBank->id)->where('user_bank_specification_id', $specification->id)->first();
                        @endphp
                        <div class="form-group">
                            <label class="font-weight-500 text-dark-blue">{{ $specification->name }}</label>
                            <input type="text" name="bank_specifications[{{ $specification->id }}]" value="{{ (!empty($selectedBankSpecification)) ? $selectedBankSpecification->value : '' }}" class="form-control" {{ ($user->financial_approval) ? 'disabled' : '' }}/>
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="form-group">
                <label class="input-label">{{ trans('financial.identity_scan') }}</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <button type="button" class="input-group-text {{ ($user->financial_approval) ? '' : 'panel-file-manager' }}" data-input="identity_scan" data-preview="holder">
                            <i data-feather="arrow-up" width="18" height="18" class="text-white"></i>
                        </button>
                    </div>
                    <input type="text" name="identity_scan" id="identity_scan" value="{{ (!empty($user) and empty($new_user)) ? $user->identity_scan : old('identity_scan') }}" class="form-control @error('identity_scan')  is-invalid @enderror" {{ ($user->financial_approval) ? 'disabled' : '' }}/>
                    @error('identity_scan')
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                    @enderror
                </div>
            </div>

            <div class="form-group">
                <label class="input-label">{{ trans('public.certificate_and_documents') }}</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <button type="button" class="input-group-text panel-file-manager" data-input="certificate" data-preview="holder">
                            <i data-feather="arrow-up" width="18" height="18" class="text-white"></i>
                        </button>
                    </div>
                    <input type="text" name="certificate" id="certificate" value="{{ (!empty($user) and empty($new_user)) ? $user->certificate : old('certificate') }}" class="form-control "/>
                </div>
            </div>

        </div>
    </div>

</section>

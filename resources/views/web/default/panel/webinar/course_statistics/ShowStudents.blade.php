@extends('web.default.panel.layouts.panel_layout')
@push('styles_top')
    <link rel="stylesheet" href="/assets/default/vendors/select2/select2.min.css">
@endpush
@section('content')
    <!-- قائمةالطلاب -->
    <section class="mt-35">
       <div class="d-flex justify-content-between">
           <h2 class="section-title">{{ trans('panel.students_list') }} {{ $webinar->title }}</h2>
           <!-- Changed button to trigger modal -->
           <button type="button" id="addStudentToCourse" class="btn btn-primary mr-2" data-toggle="modal" data-target="#addStudentModal">
               {{ trans('update.add_student_to_course') }}
           </button>
       </div>


        <!-- Add Student Modal -->
        <div class="modal fade" id="addStudentModal" tabindex="-1" role="dialog" aria-labelledby="addStudentModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addStudentModalLabel">{{ trans('update.add_student_to_course') }}</h5>

                    </div>
                    <div class="modal-body">
                        <!-- Add your form content here -->
                        <form action="/panel/webinars/{{$webinar->id}}/ShowStudents/new">
                            @csrf
                            <input type="hidden" name="webinar_id" value="{{ $webinar->id }}">

                            <div class="form-group">
                                <label for="user_id">{{ trans('public.email') }}</label>
                                <select class="form-control select-user-select2" name="user_id" data-placeholder="ابحث عن مستخدم">
                                    <option value="">ابحث عن اسم المستخدم او البريد الالكتروني</option>
                                    @foreach(\DB::table('users')->where('role_id','1')->get() as $user)
                                        <option value="{{ $user->id }}">{{ $user->full_name }} ( {{ $user->email }} )</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">{{ trans('public.submit') }}</button>
                            </div>
                        </form>
                        <form action="/panel/webinars/{{$webinar->id}}/ShowStudents/new/excel">
                            @csrf
                            <input type="hidden" name="webinar_id" value="{{ $webinar->id }}">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <button type="button" class="input-group-text panel-file-manager" data-input="excel_file" data-preview="holder">
                                        <i data-feather="arrow-up" width="18" height="18" class="text-white"></i>
                                    </button>
                                </div>
                                <input type="text" name="excel_file" id="excel_file" class="form-control value="{{ !empty($upcomingCourse) ? $upcomingCourse->thumbnail :''}}" @error('excel_file')  is-invalid @enderror" placeholder="{{ trans('forms.course_trainees') }}"/>
                                @error('excel_file')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">{{ trans('public.submit') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @if(!empty($students) and !$students->isEmpty())
            <div class="panel-section-card py-20 px-25 mt-20">
                <div class="row">
                    <a href="/panel/webinars/{{ $webinar->id }}/export-students-list"  class="d-none d-lg-flex btn btn-sm btn-primary rounded-pill" style="margin-right: 20px; margin-bottom: 30px;">
                        {{ trans('public.export_list') }}
                    </a>


                    <div class="col-12 ">
                        <div class="table-responsive">
                            <table class="table custom-table text-center ">
                                <thead>
                                <tr>
                                    <th class="text-left text-gray">{{ trans('quiz.student') }}</th>
                                    <th class="text-left text-gray">{{ trans('panel.name') }}</th>
                                    <th class="text-left text-gray">{{ trans('public.email') }}</th>
                                    <th class="text-left text-gray">{{ trans('public.mobile') }}</th>
                                    <th class="text-center text-gray">{{ trans('update.progress') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @php
                                    $usersLists = new \Illuminate\Support\Collection($students->items());
                                    $usersLists = $usersLists->merge($unregisteredUsers);
                                @endphp

                                @foreach($usersLists as $user)

                                    <tr>
                                        <td class="text-left">
                                            <div class="user-inline-avatar d-flex align-items-center">
                                                <div class="avatar bg-gray200">
                                                    <img src="{{ $user->getAvatar() }}" class="img-cover" alt="">
                                                </div>
                                                <div class=" ml-5">
                                                    <span class="d-block text-dark-blue font-weight-500">{{ $user->full_name }}</span>
                                                    <span class="mt-5 d-block font-12 text-gray">{{ $user->email }}</span>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="text-left">
                                            <span class="d-block text-dark-blue font-weight-500">{{ $user->full_name }}</span>
                                        </td>

                                        <td class="text-left">
                                            <span class="mt-5 d-block font-12 text-gray">{{ $user->email }}</span>
                                        </td>

                                        <td class="text-left">
                                            <span class="mt-5 d-block font-12 text-gray">{{ $user->mobile }}</span>
                                        </td>

                                        <td class="align-middle">
                                            <span class="text-dark-blue font-weight-500">{{ $user->course_progress ?? 0 }}%</span>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="my-30">
                {{ $students->appends(request()->input())->links('vendor.pagination.panel') }}
            </div>
        @else

            @include(getTemplate() . '.includes.no-result',[
                'file_name' => 'studentt.png',
                'title' => trans('update.course_statistic_students_no_result'),
                'hint' =>  nl2br(trans('update.course_statistic_students_no_result_hint')),
            ])
        @endif

    </section>
@endsection
@push('scripts_bottom')
    <script src="/assets/default/vendors/sweetalert2/dist/sweetalert2.min.js"></script>
    <script src="/assets/default/vendors/select2/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            // Handle form submission
            $('#addStudentForm').on('submit', function(e) {
                e.preventDefault();

                $.ajax({
                    url: '/panel/webinars/add-student',
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        if(response.status == 'success') {
                            $('#addStudentModal').modal('hide');
                            Swal.fire({
                                icon: 'success',
                                title: response.message,
                                showConfirmButton: false,
                                timer: 2000
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: xhr.responseJSON.message || 'Something went wrong'
                        });
                    }
                });
            });
        });

        $(document).ready(function() {
            // Initialize Select2 when modal is shown
            $('#addStudentModal').on('shown.bs.modal', function() {
                $('.select-user-select2').select2({
                    dropdownParent: $('#addStudentModal'), // IMPORTANT: Connect to modal
                    minimumInputLength: 3,
                    templateSelection: formatUserSelection
                });
            });

            // Clean up when modal closes
            $('#addStudentModal').on('hidden.bs.modal', function() {
                $('.select-user-select2').select2('destroy');
            });

            function formatUserSelection(user) {
                return user.text || user.email;
            }
        });
    </script>
@endpush

@extends('web.default.layouts.email')

@section('body')
    <!-- content -->
    <td valign="top" class="bodyContent" mc:edit="body_content">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header" style="font-weight: bold;">استعادة كلمة المرور الخاصة بحسابك على معهد نهج المعرفة</div>
                        <div class="card-body">
                            <div>
                            لقد طلبت استعادة كلمة المرور الخاصة بحسابك على معهد نهج المعرفة. يرجى النقر على الزر أدناه لإعادة تعيين كلمة المرور الخاصة بك:
                            </div>
                            <a href="{{ url('/reset-password/'.$token.'?email='.$email) }}">أنقر هنا لتحديث كلمة المرور</a>
                            <div style="margin-top: 20px;font-size: 13px;">إذا لم تطلب استعادة كلمة المرور، يمكنك تجاهل هذه الرسالة بأمان.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </td>
@endsection

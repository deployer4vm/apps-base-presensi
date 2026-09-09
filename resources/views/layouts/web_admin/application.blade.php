<!DOCTYPE html>

<html lang="{{ app()->getLocale() }}" class="default-style">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge,chrome=1">
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('AppConfig.system.template.admin.title') }}</title>

    <link rel="shortcut icon" href="{{asset(config('AppConfig.system.template.favicon','assets/images/favicon.ico'))}}"/>

    <!-- Main font -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,300i,400,400i,500,500i,700,700i,900" rel="stylesheet">

    <!-- Icons. Uncomment required icon fonts -->
    @if(config('AppConfig.system.web_admin.assets_template.font.fontawesome'))
    <link rel="stylesheet" href="{{ asset('/dist/vendor/fonts/fontawesome.css') }}">
    @endif
    @if(config('AppConfig.system.web_admin.assets_template.font.ionicons'))
    <link rel="stylesheet" href="{{ asset('/dist/vendor/fonts/ionicons.css') }}">
    @endif
    @if(config('AppConfig.system.web_admin.assets_template.font.linearicons'))
    <link rel="stylesheet" href="{{ asset('/dist/vendor/fonts/linearicons.css') }}">
    @endif
    @if(config('AppConfig.system.web_admin.assets_template.font.open-iconic'))
    <link rel="stylesheet" href="{{ asset('/dist/vendor/fonts/open-iconic.css') }}">
    @endif
    @if(config('AppConfig.system.web_admin.assets_template.font.pe-icon-7-stroke'))
    <link rel="stylesheet" href="{{ asset('/dist/vendor/fonts/pe-icon-7-stroke.css') }}">
    @endif
    @if (config('AppConfig.system.web_admin.assets_template.font.uicons'))
        <link rel="stylesheet" href="{{ asset('/dist/vendor/fonts/uicons-all.css') }}">
    @endif

    <!-- Core stylesheets -->
    <link rel="stylesheet" href="{{ asset('/dist/css/bootstrap.css') }}">
    <link rel="stylesheet" href="{{ asset('/dist/css/appwork.css') }}">
    <link rel="stylesheet" href="{{ asset('/dist/css/theme-app.css') }}">
    <link rel="stylesheet" href="{{ asset('/dist/css/colors.css') }}">
    <link rel="stylesheet" href="{{ asset('/dist/css/uikit.css') }}">
    <link rel="stylesheet" href="{{ asset('/dist/css/style.css') }}">

    @if(config('AppConfig.system.web_admin.assets_link'))
    @foreach (config('AppConfig.system.web_admin.assets_link') as $value)
    <link rel="stylesheet" href="{{ asset($value) }}">
    @endforeach
    @endif

    <!-- Load polyfills -->
    <script src="{{ asset('/dist/vendor/webjs/polyfills.js') }}"></script>
    <script>
        var localUrl = {
            logout: "{{route('auth.logout')}}",
            login: "{{route('auth.login')}}"
        };
        @if(config('AppConfig.system.multitenant.active'))
            var tenantId = {{config('tenant.id','false')}};
            var isOnTenantManager = {{config('tenant.isOnTenantManager',false)?'true':'false'}};
        @else
            var tenantId = false;
            var isOnTenantManager = false;
        @endif
    </script>

    <!-- Layout helpers -->
    <script src="{{ asset('/dist/vendor/js/layout-helpers.js') }}"></script>

    <!-- Libs -->

    <!-- `perfect-scrollbar` library required by SideNav plugin -->
    <link rel="stylesheet" href="{{ asset('/dist/vendor/weblibs/perfect-scrollbar/perfect-scrollbar.css') }}">
    <link rel="stylesheet" href="{{ asset('/dist/vendor/weblibs/toastr/toastr.css') }}">

    @yield('styles')

    <!-- Application stylesheets -->
    <link rel="stylesheet" href="{{ asset('/dist/vendor/webcss/application.css') }}">

</head>
<body>

    @yield('layout-content')

    @if(config('AppConfig.system.web_admin.assets_js'))
    @foreach (config('AppConfig.system.web_admin.assets_js') as $value)
    <script src="{{ asset($value) }}"></script>
    @endforeach
    @endif

    <!-- Core scripts -->
    <!-- <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script> -->

    <script src="{{ asset('/dist/vendor/weblibs/jquery/3.2.1/jquery.min.js') }}"></script>
    <script src="{{ asset('/dist/vendor/weblibs/popper/popper.js') }}"></script>
    <script src="{{ asset('/dist/vendor/webjs/bootstrap.js') }}"></script>
    <script src="{{ asset('/dist/vendor/webjs/sidenav.js') }}"></script>

    <!-- Libs -->

    <!-- `perfect-scrollbar` library required by SideNav plugin -->
    <script src="{{ asset('/dist/vendor/weblibs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('/dist/vendor/weblibs/toastr/toastr.js') }}"></script>

    <!-- Application javascripts -->
    <script src="{{ mix('/dist/webapp.js') }}"></script>

    @include('component.alertModal')

    <script>
        @if(UserAuth::isLogin())
        //set token di LocalApi
        <?php //var_dump(UserAuth::getToken('api_token'));die(); ?>
        window.axios.defaults.headers.common['Authorization'] = 'Bearer <?php echo UserAuth::getToken('api_token') ?>';
        @endif
        //convert array to query string
        function params(object) {
            var parameters = [];
            for (var property in object) {
                if (object.hasOwnProperty(property)) {
                    if(object[property]!=null)
                        parameters.push(encodeURI(property + '=' + object[property]));
                }
            }

            return parameters.join('&');
        }

        //convert query string to array
        function parseQuery(queryString) {
            var query = {};
            var pairs = (queryString[0] === '?' ? queryString.substr(1) : queryString).split('&');
            for (var i = 0; i < pairs.length; i++) {
                var pair = pairs[i].split('=');
                if(pair[0]!="")
                    query[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1] || '');
            }
            return query;
        }

        //parsing error local api
        function localApiErrorParse(res) {

            let err = { status: 400, message: "request error" , errors: []};
            //jika error server
            if (!res.data) {
                err.message = res.message;
            } else {
                err.message = res.data.message;
                err.status = res.data.status;

                if(res.data.errors){
                    err.errors = res.data.errors;
                    _.forEach(res.data.errors,(v,i)=>{
                        if(v!=true)err.message += "<br> - " + v;
                    });
                }
            }

            return err;
        }
    </script>

    @if(config('AppConfig.system.has_editor',true))
    <script>
        var editorUrl = {
            filemanager: '{{config("AppConfig.system.multitenant.active",false)?route("sys.editor.filemanager",["group_app"=>config("tenant.group_app")]):route("sys.editor.filemanager")}}',
            upload: '{{config("AppConfig.system.multitenant.active",false)?route("sys.editor.upload",["group_app"=>config("tenant.group_app")]):route("sys.editor.upload")}}'
        };
    </script>
    <script src="{{ asset('/dist/vendor/libs/kindeditor/kindeditor.js') }}"></script>
    <script src="{{ asset('/dist/vendor/libs/kindeditor/lang/en.js') }}"></script>
    @endif

    @yield('scripts')

</body>
</html>

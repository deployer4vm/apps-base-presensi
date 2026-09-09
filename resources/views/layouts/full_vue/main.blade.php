<!DOCTYPE html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="default-style">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge,chrome=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="description" content="">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('AppConfig.system.template.admin.title') }}</title>

    <link rel="shortcut icon" href="{{asset(config('AppConfig.system.template.favicon','assets/images/favicon.ico'))}}"/>

    <!-- Main font -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,300i,400,400i,500,500i,700,700i,900"
        rel="stylesheet">

    <!-- Icons. Uncomment required icon fonts -->
    @if (config('AppConfig.system.web_admin.assets_template.font.fontawesome'))
        <link rel="stylesheet" href="{{ asset('/dist/vendor/fonts/fontawesome.css') }}">
    @endif
    @if (config('AppConfig.system.web_admin.assets_template.font.ionicons'))
        <link rel="stylesheet" href="{{ asset('/dist/vendor/fonts/ionicons.css') }}">
    @endif
    @if (config('AppConfig.system.web_admin.assets_template.font.linearicons'))
        <link rel="stylesheet" href="{{ asset('/dist/vendor/fonts/linearicons.css') }}">
    @endif
    @if (config('AppConfig.system.web_admin.assets_template.font.open-iconic'))
        <link rel="stylesheet" href="{{ asset('/dist/vendor/fonts/open-iconic.css') }}">
    @endif
    @if (config('AppConfig.system.web_admin.assets_template.font.pe-icon-7-stroke'))
        <link rel="stylesheet" href="{{ asset('/dist/vendor/fonts/pe-icon-7-stroke.css') }}">
    @endif
    @if (config('AppConfig.system.web_admin.assets_template.font.uicons'))
        <link rel="stylesheet" href="{{ asset('/dist/vendor/fonts/uicons-all.css') }}">
    @endif

    <link href="{{ asset('/dist/css/bootstrap.css') }}" rel="stylesheet">
    <link href="{{ asset('/dist/css/appwork.css') }}" rel="stylesheet">
    <link href="{{ asset('/dist/css/theme-app.css') }}" rel="stylesheet">
    <link href="{{ asset('/dist/css/colors.css') }}" rel="stylesheet">
    <link href="{{ asset('/dist/css/uikit.css') }}" rel="stylesheet">
    <link href="{{ asset('/dist/css/style.css') }}" rel="stylesheet">
    <link href="{{ mix('/dist/css/authentication.css') }}" rel="stylesheet">

    @if (config('AppConfig.system.web_admin.assets_link'))
        @foreach (config('AppConfig.system.web_admin.assets_link') as $value)
            <link rel="stylesheet" href="{{ asset($value) }}">
        @endforeach
    @endif

    <script>
        @if (config('AppConfig.system.multitenant.active'))
            var tenantId = {{ config('tenant.id', 'false') }};
            var isOnTenantManager = {{ config('tenant.isOnTenantManager', false) ? 'true' : 'false' }};
            var tenantGroupApp = <?php echo config('tenant.group_app')?('"'.config('tenant.group_app').'"'):'false'; ?>;
        @else
            var tenantId = false;
            var isOnTenantManager = false;
            var tenantGroupApp = false;
        @endif
        var onIframe = {{  config('AppConfig.system.coop_var.force_on_iframe', config()->has('AppConfig.system.coop_var')) || request('onIframeConfig') ? 'true' : 'false' }};
        @if (config('AppConfig.system.coop_var.force_on_iframe', config()->has('AppConfig.system.coop_var')) || request('onIframeConfig'))
            var byPassViewConfig = {
                showNavbar: {{ config('AppConfig.system.coop_var.force_on_iframe', config()->has('AppConfig.system.coop_var')) || request('onIframeConfig.showNavbar', config()->has('AppConfig.system.coop_var')) == 0 ? '0' : '1' }},
                showSidenav: {{ config('AppConfig.system.coop_var.force_on_iframe', config()->has('AppConfig.system.coop_var')) || request('onIframeConfig.showSidenav', config()->has('AppConfig.system.coop_var')) == 0 ? '0' : '1' }},
                showFooter: {{ config('AppConfig.system.coop_var.force_on_iframe', config()->has('AppConfig.system.coop_var')) || request('onIframeConfig.showFooter', config()->has('AppConfig.system.coop_var')) == 0 ? '0' : '1' }}
            };
        @endif
        var first_login = {{ request('first_login') ? 'true' : 'false' }};
        <?php // {{ config('AppConfig.system.coop_var.force_on_iframe', true) }} ?>
    </script>

    <style>
        .app-splash-screen {
            background: #fff;
            position: fixed;
            display: block;
            z-index: 99999999;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            opacity: 1;
            transition: opacity .3s;
        }
        .app-splash-screen-content {
            text-align: center;
            position: absolute;
            top: 50%;
            left: 50%;
        }
        .app-splash-screen-content-inner {
            position: relative;
            top: -100px;
            left: -50%;

        }
        .app-splash-screen-content .logo {
            max-width: 70px;
            max-height: 70px;
        }
        .lds-ring {
            display: inline-block;
            position: relative;
            width: 80px;
            height: 80px;
        }
        .lds-ring div {
            box-sizing: border-box;
            display: block;
            position: absolute;
            width: 64px;
            height: 64px;
            margin: 8px;
            border: 8px solid rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            animation: lds-ring 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
            border-color: rgba(0, 0, 0, 0.5) transparent transparent transparent;
        }
        .lds-ring div:nth-child(1) {
            animation-delay: -0.45s;
        }
        .lds-ring div:nth-child(2) {
            animation-delay: -0.3s;
        }
        .lds-ring div:nth-child(3) {
            animation-delay: -0.15s;
        }
        @keyframes lds-ring {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        #warningbox.hide {
            display: none;
        }

        #warningbox.show {
            display: block;
        }
    </style>
</head>
<body>

    <!-- Splash screen -->
    <div class="app-splash-screen">
        <div class="app-splash-screen-content">
            <div class="app-splash-screen-content-inner">
                @if (config('AppConfig.system.template.logo'))
                    <img class="logo" src="{{ asset(config('AppConfig.system.template.logo')) }}">
                @endif
                <div class="text-large font-weight-bolder">{{ config('AppConfig.system.template.admin.title') }}</div>
                <div>{{ config('AppConfig.system.template.admin.footer.text') }}</div>
                <hr>
                <div>
                    <div class="lds-ring">
                        <div></div>
                        <div></div>
                        <div></div>
                        <div></div>
                    </div>
                </div>
                <small class="text-light">{{ __('lang.system_app_loading_text') }}</small>

                <div id="warningbox" class="card bg-warning hide" style="position: absolute; top:0; left:0;">
                    <div class="card-body">
                        <h5 class="card-title">{{ __('alert.system_app_load_fail_title') }}</h5>
                        <p class="card-text">{{ __('alert.system_app_load_fail_text') }}</p>
                        <a href="{{ url('/') }}"
                            class="btn btn-danger">{{ __('lang.system_app_load_button_text') }}</a>
                    </div>
                </div>

                <script>
                    // Remove initial splash screen
                    var splashScreen;
                    setTimeout(function() {
                        splashScreen = document.querySelector(".app-splash-screen");
                        var warning = document.getElementById('warningbox');
                        //jika splashscreen masih ada berarti error
                        if (splashScreen) {
                            warning.classList.remove('hide');
                            warning.classList.add('show');
                        }
                    }, 60000);
                </script>
            </div>
        </div>

    </div>
    <!-- / Splash screen -->

    <div id="app"></div>

    @if (config('AppConfig.system.web_admin.assets_js'))
        @foreach (config('AppConfig.system.web_admin.assets_js') as $value)
            <script src="{{ asset($value) }}"></script>
        @endforeach
    @endif

    <!-- Layout helpers -->
    <script src="{{ asset('/dist/vendor/js/layout-helpers.js') }}"></script>
    <script src="{{ mix('/dist/app.js') }}"></script>

    @if (config('AppConfig.system.has_editor', true))
        <script>
            var editorUrl = {
                filemanager: '{{ config('AppConfig.system.multitenant.active', false) ? route('sys.editor.filemanager', ['group_app' => config('tenant.group_app')]) : route('sys.editor.filemanager') }}',
                upload: '{{ config('AppConfig.system.multitenant.active', false) ? route('sys.editor.upload', ['group_app' => config('tenant.group_app')]) : route('sys.editor.upload') }}'
            };
            // Echo.channel('notif')
            //     .listen('SendNotif', (e) => {
            //         console.log('masuk cuy',e.message);
            //     });
        </script>
        <script src="{{ asset('/dist/vendor/libs/kindeditor/kindeditor.js') }}"></script>
        <script src="{{ asset('/dist/vendor/libs/kindeditor/lang/en.js') }}"></script>
    @endif
</body>
</html>

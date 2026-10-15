<!-- resources/views/errors/system-locked.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('img/logo-64.png') }}">
    <title>KUSUG-PA | 
        @if($type === 'locked') System Locked
        @elseif($type === 'payment') Payment Required
        @elseif($type === 'scheduled') Scheduled Maintenance
        @else Under Maintenance @endif
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(2deg); }
            66% { transform: translateY(-10px) rotate(-2deg); }
        }
        @keyframes float-slow {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-30px); }
        }
        @keyframes pulse-ring-locked {
            0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); }
            70% { box-shadow: 0 0 0 30px rgba(220, 38, 38, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
        }
        @keyframes pulse-ring-payment {
            0% { box-shadow: 0 0 0 0 rgba(234, 88, 12, 0.4); }
            70% { box-shadow: 0 0 0 30px rgba(234, 88, 12, 0); }
            100% { box-shadow: 0 0 0 0 rgba(234, 88, 12, 0); }
        }
        @keyframes pulse-ring-scheduled {
            0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.4); }
            70% { box-shadow: 0 0 0 30px rgba(37, 99, 235, 0); }
            100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); }
        }
        @keyframes pulse-ring-maintenance {
            0% { box-shadow: 0 0 0 0 rgba(202, 138, 4, 0.4); }
            70% { box-shadow: 0 0 0 30px rgba(202, 138, 4, 0); }
            100% { box-shadow: 0 0 0 0 rgba(202, 138, 4, 0); }
        }
        @keyframes spin-slow {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes bounce-gentle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        @keyframes scale-in {
            0% { transform: scale(0); opacity: 0; }
            80% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
        @keyframes particle-float {
            0%, 100% { transform: translateY(0) translateX(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100vh) translateX(100px); opacity: 0; }
        }
        .animate-float { animation: float 6s ease-in-out infinite; }
        .animate-float-slow { animation: float-slow 8s ease-in-out infinite; }
        .animate-spin-slow { animation: spin-slow 20s linear infinite; }
        .animate-bounce-gentle { animation: bounce-gentle 2s ease-in-out infinite; }
        .animate-scale-in { animation: scale-in 0.5s ease-out forwards; }
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            animation: particle-float 4s linear infinite;
        }
    </style>
</head>
<body class="bg-slate-950 min-h-screen flex items-center justify-center overflow-x-hidden overflow-y-auto relative">

    <!-- Animated Background Particles -->
    <div class="absolute inset-0 pointer-events-none" id="particles"></div>

    <!-- Floating Orbs -->
    <div class="absolute top-1/4 left-1/4 w-96 h-96 rounded-full blur-3xl opacity-10 animate-float-slow"
        style="background: @if($type === 'payment') #ea580c @elseif($type === 'maintenance') #ca8a04 @elseif($type === 'scheduled') #2563eb @else #dc2626 @endif; animation-delay: 0s;"></div>
    <div class="absolute bottom-1/4 right-1/4 w-80 h-80 rounded-full blur-3xl opacity-10 animate-float-slow"
        style="background: @if($type === 'payment') #ea580c @elseif($type === 'maintenance') #ca8a04 @elseif($type === 'scheduled') #2563eb @else #dc2626 @endif; animation-delay: 4s;"></div>

    <!-- Decorative Corner Elements -->
    <div class="absolute top-10 left-10 text-4xl opacity-5 animate-float"
        style="color: @if($type === 'payment') #ea580c @elseif($type === 'maintenance') #ca8a04 @elseif($type === 'scheduled') #2563eb @else #dc2626 @endif; animation-delay: 1s;">
        <i class="fas fa-seedling"></i>
    </div>
    <div class="absolute top-20 right-20 text-3xl opacity-5 animate-float"
        style="color: @if($type === 'payment') #ea580c @elseif($type === 'maintenance') #ca8a04 @elseif($type === 'scheduled') #2563eb @else #dc2626 @endif; animation-delay: 3s;">
        <i class="fas fa-tractor"></i>
    </div>
    <div class="absolute bottom-20 left-20 text-2xl opacity-5 animate-float"
        style="color: @if($type === 'payment') #ea580c @elseif($type === 'maintenance') #ca8a04 @elseif($type === 'scheduled') #2563eb @else #dc2626 @endif; animation-delay: 2s;">
        <i class="fas fa-wheat-awn"></i>
    </div>
    <div class="absolute bottom-10 right-10 text-5xl opacity-5 animate-float"
        style="color: @if($type === 'payment') #ea580c @elseif($type === 'maintenance') #ca8a04 @elseif($type === 'scheduled') #2563eb @else #dc2626 @endif; animation-delay: 4s;">
        <i class="fas fa-shield-halved"></i>
    </div>

    <!-- Spinning Ring Decorations -->
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] border rounded-full opacity-5 animate-spin-slow"
        style="border-color: @if($type === 'payment') #ea580c @elseif($type === 'maintenance') #ca8a04 @elseif($type === 'scheduled') #2563eb @else #dc2626 @endif; border-width: 2px; border-style: dashed;"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[400px] h-[400px] border rounded-full opacity-5 animate-spin-slow"
        style="border-color: @if($type === 'payment') #ea580c @elseif($type === 'maintenance') #ca8a04 @elseif($type === 'scheduled') #2563eb @else #dc2626 @endif; border-width: 1px; animation-direction: reverse; animation-duration: 15s;"></div>

    <div class="text-center px-4 z-10">
        <!-- Animated Icon Container -->
        <div class="relative inline-block mb-8 animate-scale-in">
            <!-- Outer Ring Pulse -->
            <div class="absolute inset-0 w-28 h-28 rounded-full mx-auto"
                style="animation: @if($type === 'payment') pulse-ring-payment @elseif($type === 'maintenance') pulse-ring-maintenance @elseif($type === 'scheduled') pulse-ring-scheduled @else pulse-ring-locked @endif 2s infinite;">
            </div>

            <!-- Icon Background -->
            <div class="relative w-28 h-28 rounded-full flex items-center justify-center mx-auto"
                style="background: @if($type === 'payment') rgba(234, 88, 12, 0.15) @elseif($type === 'maintenance') rgba(202, 138, 4, 0.15) @elseif($type === 'scheduled') rgba(37, 99, 235, 0.15) @else rgba(220, 38, 38, 0.15) @endif; backdrop-filter: blur(20px); border: 2px solid @if($type === 'payment') rgba(234, 88, 12, 0.3) @elseif($type === 'maintenance') rgba(202, 138, 4, 0.3) @elseif($type === 'scheduled') rgba(37, 99, 235, 0.3) @else rgba(220, 38, 38, 0.3) @endif;">

                @if($type === 'payment')
                    <div class="relative">
                        <i class="fas fa-file-invoice text-5xl animate-float" style="color: #ea580c;"></i>
                        <i class="fas fa-lock text-2xl absolute -bottom-1 -right-1 animate-bounce-gentle" style="color: #fbbf24; animation-delay: 0.5s;"></i>
                    </div>
                @elseif($type === 'maintenance')
                    <div class="relative flex items-center gap-1">
                        <i class="fas fa-cog text-3xl animate-spin-slow" style="color: #ca8a04; animation-duration: 4s;"></i>
                        <i class="fas fa-wrench text-4xl animate-float" style="color: #ca8a04; animation-delay: 0.5s;"></i>
                        <i class="fas fa-cog text-3xl animate-spin-slow" style="color: #ca8a04; animation-direction: reverse; animation-duration: 3s;"></i>
                    </div>
                @elseif($type === 'scheduled')
                    <div class="relative">
                        <i class="fas fa-calendar-xmark text-5xl animate-bounce-gentle" style="color: #2563eb;"></i>
                        <i class="fas fa-clock text-2xl absolute -bottom-1 -right-1 animate-pulse" style="color: #60a5fa;"></i>
                    </div>
                @else
                    <div class="relative">
                        <i class="fas fa-shield-halved text-5xl animate-bounce-gentle" style="color: #dc2626;"></i>
                        <div class="absolute -top-2 -right-2 flex gap-0.5">
                            <span class="w-2 h-2 rounded-full animate-pulse" style="background: #ef4444; animation-delay: 0s;"></span>
                            <span class="w-2 h-2 rounded-full animate-pulse" style="background: #ef4444; animation-delay: 0.3s;"></span>
                            <span class="w-2 h-2 rounded-full animate-pulse" style="background: #ef4444; animation-delay: 0.6s;"></span>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Title with gradient effect -->
        <h1 class="text-4xl font-extrabold mb-4 bg-clip-text text-transparent bg-gradient-to-r animate-pulse"
            style="background-image: linear-gradient(135deg, @if($type === 'payment') #ea580c, #fbbf24, #ea580c @elseif($type === 'maintenance') #ca8a04, #fbbf24, #ca8a04 @elseif($type === 'scheduled') #2563eb, #60a5fa, #2563eb @else #dc2626, #ef4444, #dc2626 @endif); background-size: 200% 200%;">
            @if($type === 'payment') Payment Required
            @elseif($type === 'maintenance') Under Maintenance
            @elseif($type === 'scheduled') Scheduled Lock
            @else System Locked @endif
        </h1>

        <!-- Status Indicator -->
        <div class="flex items-center justify-center gap-2 mb-4">
            <span class="relative flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"
                    style="background: @if($type === 'payment') #ea580c @elseif($type === 'maintenance') #ca8a04 @elseif($type === 'scheduled') #2563eb @else #dc2626 @endif;"></span>
                <span class="relative inline-flex rounded-full h-3 w-3"
                    style="background: @if($type === 'payment') #ea580c @elseif($type === 'maintenance') #ca8a04 @elseif($type === 'scheduled') #2563eb @else #dc2626 @endif;"></span>
            </span>
            <span class="text-sm font-medium uppercase tracking-wider"
                style="color: @if($type === 'payment') #ea580c @elseif($type === 'maintenance') #ca8a04 @elseif($type === 'scheduled') #2563eb @else #dc2626 @endif;">
                @if($type === 'payment') Access Suspended
                @elseif($type === 'maintenance') Temporarily Unavailable
                @elseif($type === 'scheduled') Scheduled Downtime
                @else Access Denied @endif
            </span>
        </div>

        <!-- Message Card -->
        <div class="max-w-lg mx-auto bg-slate-800/50 backdrop-blur-xl rounded-2xl p-6 border border-slate-700/50 shadow-2xl">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 mt-0.5">
                    @if($type === 'payment')
                        <i class="fas fa-exclamation-triangle text-2xl text-amber-400 animate-pulse"></i>
                    @elseif($type === 'maintenance')
                        <i class="fas fa-clock text-2xl text-amber-400 animate-pulse"></i>
                    @elseif($type === 'scheduled')
                        <i class="fas fa-calendar text-2xl text-blue-400 animate-pulse"></i>
                    @else
                        <i class="fas fa-shield-halved text-2xl text-red-400 animate-pulse"></i>
                    @endif
                </div>
                <div class="text-left">
                    <p class="text-slate-300 text-sm leading-relaxed">{{ $message }}</p>
                </div>
            </div>
        </div>

        {{-- <!-- Auto Refresh Timer -->
        <div class="mt-6 max-w-lg mx-auto bg-slate-800/30 backdrop-blur-xl rounded-2xl p-4 border border-slate-700/30" x-data="{ seconds: 30, progress: 100 }" x-init="startCountdown()">
            <p class="text-slate-500 text-xs uppercase tracking-wider mb-3">Auto-refreshing in</p>
            <div class="text-3xl font-bold text-white font-mono" x-text="seconds + 's'"></div>
            <div class="w-full h-1 bg-slate-700/50 rounded-full mt-3 overflow-hidden">
                <div class="h-full rounded-full transition-all duration-1000" :style="'width: ' + progress + '%'"
                    style="background: @if($type === 'payment') #ea580c @elseif($type === 'maintenance') #ca8a04 @elseif($type === 'scheduled') #2563eb @else #dc2626 @endif;"></div>
            </div>
        </div> --}}

        <!-- Contact Info -->
        <div class="mt-8 flex items-center justify-center gap-2 text-slate-500 text-sm">
            <i class="fas fa-headset"></i>
            <span>Please contact the</span>
            <span class="text-white font-semibold">System Administrator</span>
            <span>for assistance.</span>
        </div>

        <!-- Back to Login -->
        <a href="{{ route('getLogin') }}" 
           class="mt-6 inline-flex items-center gap-2 text-slate-500 hover:text-white transition-colors text-sm">
            <i class="fas fa-arrow-left"></i> Back to Login
        </a>

        <!-- Branding -->
        <div class="mt-12 text-slate-600 text-xs">
            <p>KUSUG-PA Check & Voucher System &copy; {{ date('Y') }}</p>
            <p class="mt-1 opacity-50">Sugarcane Crop Management System</p>
        </div>
    </div>

    <script>
        function startCountdown() {
            const totalSeconds = 30;
            const endTime = Date.now() + (totalSeconds * 1000);
            
            setInterval(() => {
                const remaining = Math.max(0, Math.ceil((endTime - Date.now()) / 1000));
                this.seconds = remaining;
                this.progress = (remaining / totalSeconds) * 100;
                if (remaining <= 0) location.reload();
            }, 200);
        }

        const particlesContainer = document.getElementById('particles');
        const particleColor = '@if($type === "payment") #ea580c @elseif($type === "maintenance") #ca8a04 @elseif($type === "scheduled") #2563eb @else #dc2626 @endif';
        
        for (let i = 0; i < 20; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.top = Math.random() * 100 + '%';
            particle.style.background = particleColor;
            particle.style.animationDelay = Math.random() * 4 + 's';
            particle.style.animationDuration = (Math.random() * 3 + 3) + 's';
            particlesContainer.appendChild(particle);
        }
    </script>
</body>
</html>

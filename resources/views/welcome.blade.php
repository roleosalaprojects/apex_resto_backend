<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Richard Leosala Computer Programming Services - Custom software development, web applications, and digital solutions.">
    <title>RLCPS | Richard Leosala Computer Programming Services</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        void: '#050810',
                        deep: '#0a0e1a',
                        surface: '#111827',
                        border: '#1e293b',
                        muted: '#64748b',
                        text: '#e2e8f0',
                        cyan: '#00d4ff',
                    },
                    fontFamily: {
                        mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
                        sans: ['DM Sans', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'DM Sans', system-ui, sans-serif;
            background: #050810;
            color: #e2e8f0;
        }

        /* Grid pattern background */
        .grid-pattern {
            background-image:
                linear-gradient(rgba(0, 212, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 212, 255, 0.03) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        .grid-pattern-dense {
            background-image:
                linear-gradient(rgba(0, 212, 255, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 212, 255, 0.05) 1px, transparent 1px);
            background-size: 20px 20px;
        }

        /* Glow effects */
        .glow-text {
            text-shadow: 0 0 40px rgba(0, 212, 255, 0.5), 0 0 80px rgba(0, 212, 255, 0.3);
        }

        .glow-line {
            background: linear-gradient(90deg, transparent, #00d4ff, transparent);
            height: 1px;
        }

        /* Terminal cursor */
        .cursor-blink::after {
            content: '_';
            animation: blink 1s step-end infinite;
        }

        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0; }
        }

        /* Gradient text */
        .gradient-text {
            background: linear-gradient(135deg, #00d4ff, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Card hover */
        .service-card {
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.8), rgba(10, 14, 26, 0.9));
            border: 1px solid #1e293b;
            transition: all 0.3s ease;
        }

        .service-card:hover {
            border-color: #00d4ff;
            box-shadow: 0 0 40px rgba(0, 212, 255, 0.15);
            transform: translateY(-4px);
        }

        /* Stats counter */
        .stat-value {
            background: linear-gradient(180deg, #e2e8f0, #64748b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Navigation */
        .nav-link {
            position: relative;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: #00d4ff;
            transition: width 0.3s ease;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        /* Button styles */
        .btn-primary {
            background: #00d4ff;
            color: #050810;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            box-shadow: 0 0 30px rgba(0, 212, 255, 0.5);
            transform: translateY(-2px);
        }

        .btn-outline {
            border: 1px solid #00d4ff;
            color: #00d4ff;
            transition: all 0.3s ease;
        }

        .btn-outline:hover {
            background: #00d4ff;
            color: #050810;
        }

        /* Floating geometric shapes */
        .geo-shape {
            position: absolute;
            border: 1px solid rgba(0, 212, 255, 0.2);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        /* Scroll indicator */
        .scroll-indicator {
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }

        /* Icon box hover */
        .icon-box {
            transition: background-color 0.3s ease;
        }

        .group:hover .icon-box {
            background-color: rgba(0, 212, 255, 0.1);
        }

        /* Social icon hover */
        .social-icon {
            transition: all 0.3s ease;
        }

        .social-icon:hover {
            border-color: #00d4ff;
            background-color: rgba(0, 212, 255, 0.1);
        }
    </style>
</head>
<body class="antialiased overflow-x-hidden">
    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 backdrop-blur-md bg-void/80 border-b border-border">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Logo -->
                <a href="/" class="flex items-center gap-3">
                    <div class="w-10 h-10 border border-cyan flex items-center justify-center">
                        <span class="font-mono text-cyan font-bold text-lg">RL</span>
                    </div>
                    <span class="font-mono text-sm tracking-wider hidden sm:block text-text">RLCPS</span>
                </a>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center gap-8">
                    <a href="#services" class="nav-link font-mono text-sm tracking-wide text-muted hover:text-text transition-colors">Services</a>
                    <a href="#process" class="nav-link font-mono text-sm tracking-wide text-muted hover:text-text transition-colors">Process</a>
                    <a href="#about" class="nav-link font-mono text-sm tracking-wide text-muted hover:text-text transition-colors">About</a>
                    <a href="#contact" class="nav-link font-mono text-sm tracking-wide text-muted hover:text-text transition-colors">Contact</a>
                </div>

                <!-- CTA Button & Mobile Menu Toggle -->
                <div class="flex items-center gap-4">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ route('admin.home') }}" class="btn-primary px-6 py-2.5 font-mono text-sm tracking-wide hidden sm:block">Dashboard</a>
                        @else
                            <a href="/shop" class="btn-outline px-6 py-2.5 font-mono text-sm tracking-wide hidden sm:block">Shop Portal</a>
                        @endauth
                    @endif

                    <!-- Mobile Menu Button -->
                    <button id="mobile-menu-btn" class="md:hidden w-10 h-10 border border-border flex items-center justify-center text-text hover:border-cyan hover:text-cyan transition-colors">
                        <svg id="menu-icon-open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                        <svg id="menu-icon-close" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Menu Overlay -->
    <div id="mobile-menu" class="fixed inset-0 z-40 hidden">
        <!-- Backdrop -->
        <div id="mobile-menu-backdrop" class="absolute inset-0 bg-void/95 backdrop-blur-md"></div>

        <!-- Menu Content -->
        <div class="relative flex flex-col items-center justify-center h-full">
            <nav class="flex flex-col items-center gap-8">
                <a href="#services" class="mobile-nav-link font-mono text-2xl tracking-wide text-text hover:text-cyan transition-colors">Services</a>
                <a href="#process" class="mobile-nav-link font-mono text-2xl tracking-wide text-text hover:text-cyan transition-colors">Process</a>
                <a href="#about" class="mobile-nav-link font-mono text-2xl tracking-wide text-text hover:text-cyan transition-colors">About</a>
                <a href="#contact" class="mobile-nav-link font-mono text-2xl tracking-wide text-text hover:text-cyan transition-colors">Contact</a>

                <div class="glow-line w-24 my-4"></div>

                @if (Route::has('login'))
                    @auth
                        <a href="{{ route('admin.home') }}" class="btn-primary px-8 py-3 font-mono text-sm tracking-wide">Dashboard</a>
                    @else
                        <a href="/shop" class="btn-outline px-8 py-3 font-mono text-sm tracking-wide">Shop</a>
                    @endauth
                @endif
            </nav>

            <!-- Decorative Elements -->
            <div class="absolute bottom-12 left-1/2 -translate-x-1/2">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 bg-cyan rounded-full animate-pulse"></span>
                    <span class="font-mono text-xs text-muted">RLCPS</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Hero Section -->
    <section class="relative min-h-screen flex items-center justify-center grid-pattern overflow-hidden">
        <!-- Geometric decorations -->
        <div class="geo-shape w-64 h-64 top-20 left-10 rotate-45 hidden lg:block" style="animation-delay: 0s;"></div>
        <div class="geo-shape w-32 h-32 top-40 right-20 hidden lg:block" style="animation-delay: 2s;"></div>
        <div class="geo-shape w-48 h-48 bottom-32 left-1/4 rotate-12 hidden lg:block" style="animation-delay: 4s;"></div>

        <!-- Gradient orb -->
        <div class="absolute top-1/4 right-1/4 w-96 h-96 bg-cyan opacity-5 blur-[150px] rounded-full"></div>
        <div class="absolute bottom-1/4 left-1/4 w-96 h-96 bg-purple-500 opacity-5 blur-[150px] rounded-full"></div>

        <div class="relative max-w-7xl mx-auto px-6 lg:px-8 pt-32 pb-20">
            <div class="text-center">
                <!-- Tagline -->
                <div class="inline-flex items-center gap-2 px-4 py-2 border border-border bg-surface/50 backdrop-blur mb-8">
                    <span class="w-2 h-2 bg-cyan rounded-full animate-pulse"></span>
                    <span class="font-mono text-xs tracking-widest text-muted uppercase">Software Development Services</span>
                </div>

                <!-- Main Heading -->
                <h1 class="font-mono text-4xl sm:text-5xl lg:text-7xl font-bold tracking-tight leading-none mb-6">
                    <span class="block text-text">We Build</span>
                    <span class="block glow-text text-cyan mt-2">Digital Solutions</span>
                </h1>

                <!-- Subheading -->
                <p class="max-w-2xl mx-auto text-lg sm:text-xl text-muted leading-relaxed mb-12">
                    Custom software development, web applications, and enterprise solutions.
                    From concept to deployment, we transform your ideas into powerful, scalable technology.
                </p>

                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-20">
                    <a href="#contact" class="btn-primary px-8 py-4 font-mono text-sm tracking-wide">
                        Start Your Project
                        <span class="ml-2">&rarr;</span>
                    </a>
                    <a href="#services" class="btn-outline px-8 py-4 font-mono text-sm tracking-wide">
                        View Services
                    </a>
                </div>

                <!-- Scroll Indicator -->
                <div class="scroll-indicator">
                    <div class="flex flex-col items-center gap-2 text-muted">
                        <span class="font-mono text-xs tracking-widest">SCROLL</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom gradient line -->
        <div class="absolute bottom-0 left-0 right-0 glow-line"></div>
    </section>

    <!-- Services Section -->
    <section id="services" class="relative py-32 bg-deep">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <!-- Section Header -->
            <div class="text-center mb-20">
                <span class="font-mono text-xs tracking-[0.3em] text-cyan uppercase">What We Do</span>
                <h2 class="font-mono text-3xl sm:text-4xl lg:text-5xl font-bold mt-4 mb-6 text-text">
                    Our <span class="gradient-text">Services</span>
                </h2>
                <div class="glow-line max-w-xs mx-auto"></div>
            </div>

            <!-- Services Grid -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Service Card 1 -->
                <div class="service-card p-8 relative group">
                    <div class="absolute top-0 right-0 font-mono text-6xl font-bold text-border opacity-50 -mt-2 mr-4">01</div>
                    <div class="icon-box w-14 h-14 border border-cyan flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                        </svg>
                    </div>
                    <h3 class="font-mono text-xl font-semibold mb-3 text-text">Web Development</h3>
                    <p class="text-muted leading-relaxed">
                        Modern, responsive web applications built with cutting-edge technologies. From simple websites to complex enterprise platforms.
                    </p>
                </div>

                <!-- Service Card 2 -->
                <div class="service-card p-8 relative group">
                    <div class="absolute top-0 right-0 font-mono text-6xl font-bold text-border opacity-50 -mt-2 mr-4">02</div>
                    <div class="icon-box w-14 h-14 border border-cyan flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h3 class="font-mono text-xl font-semibold mb-3 text-text">Mobile Apps</h3>
                    <p class="text-muted leading-relaxed">
                        Native and cross-platform mobile applications that deliver exceptional user experiences on iOS and Android devices.
                    </p>
                </div>

                <!-- Service Card 3 -->
                <div class="service-card p-8 relative group">
                    <div class="absolute top-0 right-0 font-mono text-6xl font-bold text-border opacity-50 -mt-2 mr-4">03</div>
                    <div class="icon-box w-14 h-14 border border-cyan flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                        </svg>
                    </div>
                    <h3 class="font-mono text-xl font-semibold mb-3 text-text">Database Design</h3>
                    <p class="text-muted leading-relaxed">
                        Optimized database architectures that ensure data integrity, security, and lightning-fast query performance.
                    </p>
                </div>

                <!-- Service Card 4 -->
                <div class="service-card p-8 relative group">
                    <div class="absolute top-0 right-0 font-mono text-6xl font-bold text-border opacity-50 -mt-2 mr-4">04</div>
                    <div class="icon-box w-14 h-14 border border-cyan flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                        </svg>
                    </div>
                    <h3 class="font-mono text-xl font-semibold mb-3 text-text">API Development</h3>
                    <p class="text-muted leading-relaxed">
                        RESTful and GraphQL APIs that power your applications with secure, scalable, and well-documented endpoints.
                    </p>
                </div>

                <!-- Service Card 5 -->
                <div class="service-card p-8 relative group">
                    <div class="absolute top-0 right-0 font-mono text-6xl font-bold text-border opacity-50 -mt-2 mr-4">05</div>
                    <div class="icon-box w-14 h-14 border border-cyan flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3 class="font-mono text-xl font-semibold mb-3 text-text">POS Systems</h3>
                    <p class="text-muted leading-relaxed">
                        Complete point-of-sale solutions with inventory management, sales tracking, and comprehensive business analytics.
                    </p>
                </div>

                <!-- Service Card 6 -->
                <div class="service-card p-8 relative group">
                    <div class="absolute top-0 right-0 font-mono text-6xl font-bold text-border opacity-50 -mt-2 mr-4">06</div>
                    <div class="icon-box w-14 h-14 border border-cyan flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <h3 class="font-mono text-xl font-semibold mb-3 text-text">Tech Support</h3>
                    <p class="text-muted leading-relaxed">
                        Ongoing maintenance, updates, and technical support to keep your systems running smoothly and securely.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Process Section -->
    <section id="process" class="relative py-32 grid-pattern-dense">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <!-- Section Header -->
            <div class="text-center mb-20">
                <span class="font-mono text-xs tracking-[0.3em] text-cyan uppercase">How We Work</span>
                <h2 class="font-mono text-3xl sm:text-4xl lg:text-5xl font-bold mt-4 mb-6 text-text">
                    Our <span class="gradient-text">Process</span>
                </h2>
                <div class="glow-line max-w-xs mx-auto"></div>
            </div>

            <!-- Process Steps -->
            <div class="grid md:grid-cols-3 gap-12 relative">
                <!-- Connector line (desktop only) -->
                <div class="hidden md:block absolute top-24 left-[16%] right-[16%] h-px bg-gradient-to-r from-transparent via-cyan to-transparent opacity-30"></div>

                <!-- Step 1 -->
                <div class="relative text-center">
                    <div class="relative inline-block mb-8">
                        <div class="w-20 h-20 border-2 border-cyan flex items-center justify-center mx-auto bg-void">
                            <span class="font-mono text-3xl font-bold text-cyan">01</span>
                        </div>
                        <div class="absolute -inset-2 border border-cyan opacity-30"></div>
                    </div>
                    <h3 class="font-mono text-xl font-semibold mb-4 text-text">Consultation</h3>
                    <p class="text-muted leading-relaxed">
                        We start by understanding your business needs, goals, and technical requirements. Every great project begins with a conversation.
                    </p>
                </div>

                <!-- Step 2 -->
                <div class="relative text-center">
                    <div class="relative inline-block mb-8">
                        <div class="w-20 h-20 border-2 border-cyan flex items-center justify-center mx-auto bg-void">
                            <span class="font-mono text-3xl font-bold text-cyan">02</span>
                        </div>
                        <div class="absolute -inset-2 border border-cyan opacity-30"></div>
                    </div>
                    <h3 class="font-mono text-xl font-semibold mb-4 text-text">Development</h3>
                    <p class="text-muted leading-relaxed">
                        Our team designs and builds your solution using industry best practices, keeping you informed at every milestone.
                    </p>
                </div>

                <!-- Step 3 -->
                <div class="relative text-center">
                    <div class="relative inline-block mb-8">
                        <div class="w-20 h-20 border-2 border-cyan flex items-center justify-center mx-auto bg-void">
                            <span class="font-mono text-3xl font-bold text-cyan">03</span>
                        </div>
                        <div class="absolute -inset-2 border border-cyan opacity-30"></div>
                    </div>
                    <h3 class="font-mono text-xl font-semibold mb-4 text-text">Deployment</h3>
                    <p class="text-muted leading-relaxed">
                        We deploy your application, provide training, and offer ongoing support to ensure your continued success.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- About / Stats Section -->
    <section id="about" class="relative py-32 bg-deep">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <!-- Left: About Text -->
                <div>
                    <span class="font-mono text-xs tracking-[0.3em] text-cyan uppercase">About Us</span>
                    <h2 class="font-mono text-3xl sm:text-4xl lg:text-5xl font-bold mt-4 mb-6 text-text">
                        Crafting Code <span class="gradient-text">Since 2018</span>
                    </h2>
                    <p class="text-muted text-lg leading-relaxed mb-6">
                        Richard Leosala Computer Programming Services is a software development company dedicated to building powerful, elegant solutions for businesses of all sizes.
                    </p>
                    <p class="text-muted leading-relaxed mb-8">
                        We believe that great software is more than just functional code—it's a tool that empowers your business to grow. Our approach combines technical excellence with a deep understanding of your unique challenges.
                    </p>

                    <!-- Quote -->
                    <blockquote class="border-l-2 border-cyan pl-6 py-4 bg-surface/50">
                        <p class="text-text italic mb-3">
                            "When you care about your topic, you'll write about it in a more powerful, emotionally expressive way."
                        </p>
                        <cite class="font-mono text-sm text-cyan">— Richard Leosala, CEO</cite>
                    </blockquote>
                </div>

                <!-- Right: Stats Grid -->
                <div class="grid grid-cols-2 gap-6">
                    <!-- Stat 1 -->
                    <div class="service-card p-8 text-center">
                        <div class="font-mono text-5xl lg:text-6xl font-bold stat-value mb-2">5+</div>
                        <div class="font-mono text-sm tracking-wider text-muted uppercase">Years Experience</div>
                    </div>

                    <!-- Stat 2 -->
                    <div class="service-card p-8 text-center">
                        <div class="font-mono text-5xl lg:text-6xl font-bold stat-value mb-2">50+</div>
                        <div class="font-mono text-sm tracking-wider text-muted uppercase">Projects Delivered</div>
                    </div>

                    <!-- Stat 3 -->
                    <div class="service-card p-8 text-center">
                        <div class="font-mono text-5xl lg:text-6xl font-bold stat-value mb-2">25+</div>
                        <div class="font-mono text-sm tracking-wider text-muted uppercase">Happy Clients</div>
                    </div>

                    <!-- Stat 4 -->
                    <div class="service-card p-8 text-center">
                        <div class="font-mono text-5xl lg:text-6xl font-bold stat-value mb-2">99%</div>
                        <div class="font-mono text-sm tracking-wider text-muted uppercase">Client Satisfaction</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="relative py-32 grid-pattern">
        <div class="max-w-6xl mx-auto px-6 lg:px-8">
            <!-- Section Header -->
            <div class="text-center mb-16">
                <span class="font-mono text-xs tracking-[0.3em] text-cyan uppercase">Get In Touch</span>
                <h2 class="font-mono text-3xl sm:text-4xl lg:text-5xl font-bold mt-4 mb-6 text-text">
                    Ready to Start <span class="gradient-text">Your Project?</span>
                </h2>
                <p class="text-muted text-lg leading-relaxed max-w-2xl mx-auto">
                    Let's discuss how we can help transform your ideas into reality. Reach out for a free consultation.
                </p>
            </div>

            <div class="grid lg:grid-cols-5 gap-12">
                <!-- Contact Form -->
                <div class="lg:col-span-3">
                    <form id="contact-form" class="service-card p-8 lg:p-10">
                        <div class="grid sm:grid-cols-2 gap-6 mb-6">
                            <!-- Name -->
                            <div>
                                <label for="name" class="block font-mono text-sm text-muted mb-2">Your Name</label>
                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    required
                                    class="w-full bg-void border border-border px-4 py-3 text-text font-sans focus:border-cyan focus:outline-none transition-colors"
                                    placeholder="John Doe"
                                >
                            </div>

                            <!-- Email -->
                            <div>
                                <label for="email" class="block font-mono text-sm text-muted mb-2">Email Address</label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    required
                                    class="w-full bg-void border border-border px-4 py-3 text-text font-sans focus:border-cyan focus:outline-none transition-colors"
                                    placeholder="john@example.com"
                                >
                            </div>
                        </div>

                        <!-- Subject -->
                        <div class="mb-6">
                            <label for="subject" class="block font-mono text-sm text-muted mb-2">Subject</label>
                            <select
                                id="subject"
                                name="subject"
                                required
                                class="w-full bg-void border border-border px-4 py-3 text-text font-sans focus:border-cyan focus:outline-none transition-colors appearance-none cursor-pointer"
                            >
                                <option value="" disabled selected>Select a topic</option>
                                <option value="web-development">Web Development</option>
                                <option value="mobile-app">Mobile App Development</option>
                                <option value="pos-system">POS System</option>
                                <option value="api-development">API Development</option>
                                <option value="database-design">Database Design</option>
                                <option value="tech-support">Tech Support</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <!-- Message -->
                        <div class="mb-6">
                            <label for="message" class="block font-mono text-sm text-muted mb-2">Your Message</label>
                            <textarea
                                id="message"
                                name="message"
                                rows="5"
                                required
                                class="w-full bg-void border border-border px-4 py-3 text-text font-sans focus:border-cyan focus:outline-none transition-colors resize-none"
                                placeholder="Tell us about your project..."
                            ></textarea>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            id="submit-btn"
                            class="btn-primary w-full py-4 font-mono text-sm tracking-wide flex items-center justify-center gap-2"
                        >
                            <span id="btn-text">Send Message</span>
                            <svg id="btn-arrow" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                            <svg id="btn-spinner" class="w-5 h-5 animate-spin hidden" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </button>

                        <!-- Success Message -->
                        <div id="success-message" class="hidden mt-6 p-4 border border-green-500 bg-green-500/10 text-center">
                            <p class="text-green-400 font-mono text-sm">Message sent successfully! We'll get back to you soon.</p>
                        </div>

                        <!-- Error Message -->
                        <div id="error-message" class="hidden mt-6 p-4 border border-red-500 bg-red-500/10 text-center">
                            <p class="text-red-400 font-mono text-sm">Something went wrong. Please try again or email us directly.</p>
                        </div>
                    </form>
                </div>

                <!-- Contact Info -->
                <div class="lg:col-span-2 flex flex-col gap-6">
                    <!-- Email Card -->
                    <a href="mailto:roleosala@gmail.com" class="service-card p-6 group flex items-center gap-4">
                        <div class="icon-box w-12 h-12 border border-cyan flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6 text-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div class="text-left">
                            <h3 class="font-mono text-sm font-semibold text-text mb-1">Email Us</h3>
                            <p class="text-cyan text-sm">roleosala@gmail.com</p>
                        </div>
                    </a>

                    <!-- Phone Card -->
                    <div class="service-card p-6 flex items-center gap-4">
                        <div class="w-12 h-12 border border-cyan flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6 text-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                        </div>
                        <div class="text-left">
                            <h3 class="font-mono text-sm font-semibold text-text mb-1">Call Us</h3>
                            <p class="text-muted text-sm">Available on request</p>
                        </div>
                    </div>

                    <!-- Location Card -->
                    <div class="service-card p-6 flex items-center gap-4">
                        <div class="w-12 h-12 border border-cyan flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6 text-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <div class="text-left">
                            <h3 class="font-mono text-sm font-semibold text-text mb-1">Location</h3>
                            <p class="text-muted text-sm">Philippines</p>
                        </div>
                    </div>

                    <!-- Social Links -->
                    <div class="service-card p-6">
                        <h3 class="font-mono text-sm font-semibold text-text mb-4">Connect With Us</h3>
                        <div class="flex gap-3">
                            <a href="https://www.facebook.com/rolworks" target="_blank" class="social-icon w-10 h-10 border border-border flex items-center justify-center text-text">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                            </a>
                            <a href="https://github.com/roleosalaprojects" target="_blank" class="social-icon w-10 h-10 border border-border flex items-center justify-center text-text">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                            </a>
                            <a href="https://www.linkedin.com" target="_blank" class="social-icon w-10 h-10 border border-border flex items-center justify-center text-text">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- Response Time -->
                    <div class="p-4 border border-dashed border-border text-center">
                        <p class="font-mono text-xs text-muted">
                            <span class="inline-block w-2 h-2 bg-green-500 rounded-full animate-pulse mr-2"></span>
                            Typical response time: 24-48 hours
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="relative py-12 bg-deep border-t border-border">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <!-- Logo & Copyright -->
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 border border-cyan flex items-center justify-center">
                        <span class="font-mono text-cyan font-bold text-lg">RL</span>
                    </div>
                    <span class="text-muted text-sm">
                        &copy; {{ date('Y') }} Richard Leosala Computer Programming Services
                    </span>
                </div>

                <!-- Status -->
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    <span class="font-mono text-xs text-muted">All systems operational</span>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Mobile Menu Toggle
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        const menuIconOpen = document.getElementById('menu-icon-open');
        const menuIconClose = document.getElementById('menu-icon-close');
        const mobileNavLinks = document.querySelectorAll('.mobile-nav-link');
        const mobileMenuBackdrop = document.getElementById('mobile-menu-backdrop');

        let isMenuOpen = false;

        function toggleMenu() {
            isMenuOpen = !isMenuOpen;

            if (isMenuOpen) {
                mobileMenu.classList.remove('hidden');
                menuIconOpen.classList.add('hidden');
                menuIconClose.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            } else {
                mobileMenu.classList.add('hidden');
                menuIconOpen.classList.remove('hidden');
                menuIconClose.classList.add('hidden');
                document.body.style.overflow = '';
            }
        }

        function closeMenu() {
            if (isMenuOpen) {
                toggleMenu();
            }
        }

        mobileMenuBtn.addEventListener('click', toggleMenu);
        mobileMenuBackdrop.addEventListener('click', closeMenu);

        // Close menu when clicking nav links
        mobileNavLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                closeMenu();

                // Small delay to allow menu to close before scrolling
                setTimeout(() => {
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }, 100);
            });
        });

        // Smooth scroll for all anchor links (desktop)
        document.querySelectorAll('a[href^="#"]:not(.mobile-nav-link)').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Close menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isMenuOpen) {
                closeMenu();
            }
        });

        // Close menu on window resize (if switching to desktop)
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768 && isMenuOpen) {
                closeMenu();
            }
        });

        // Contact Form Handler
        const contactForm = document.getElementById('contact-form');
        const submitBtn = document.getElementById('submit-btn');
        const btnText = document.getElementById('btn-text');
        const btnArrow = document.getElementById('btn-arrow');
        const btnSpinner = document.getElementById('btn-spinner');
        const successMessage = document.getElementById('success-message');
        const errorMessage = document.getElementById('error-message');
        const errorMessageText = errorMessage.querySelector('p');

        contactForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            // Hide any previous messages
            successMessage.classList.add('hidden');
            errorMessage.classList.add('hidden');

            // Show loading state
            btnText.textContent = 'Sending...';
            btnArrow.classList.add('hidden');
            btnSpinner.classList.remove('hidden');
            submitBtn.disabled = true;

            // Get form data
            const formData = new FormData(contactForm);
            const data = {
                name: formData.get('name'),
                email: formData.get('email'),
                subject: formData.get('subject'),
                message: formData.get('message')
            };

            try {
                const response = await fetch('/api/v1/contact', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    // Show success message
                    successMessage.querySelector('p').textContent = result.message;
                    successMessage.classList.remove('hidden');
                    contactForm.reset();
                } else {
                    // Handle validation errors
                    let errorText = result.message || 'Something went wrong. Please try again.';
                    if (result.errors) {
                        const firstError = Object.values(result.errors)[0];
                        errorText = Array.isArray(firstError) ? firstError[0] : firstError;
                    }
                    errorMessageText.textContent = errorText;
                    errorMessage.classList.remove('hidden');
                }
            } catch (error) {
                // Network error - show error message
                errorMessageText.textContent = 'Network error. Please check your connection and try again.';
                errorMessage.classList.remove('hidden');
            } finally {
                // Reset button state
                btnText.textContent = 'Send Message';
                btnArrow.classList.remove('hidden');
                btnSpinner.classList.add('hidden');
                submitBtn.disabled = false;
            }
        });

        // Add focus styles for select dropdown
        const selectElement = document.getElementById('subject');
        selectElement.addEventListener('focus', function() {
            this.style.borderColor = '#00d4ff';
        });
        selectElement.addEventListener('blur', function() {
            this.style.borderColor = '#1e293b';
        });
    </script>
</body>
</html>

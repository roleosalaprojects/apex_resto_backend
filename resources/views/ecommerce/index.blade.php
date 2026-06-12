<x-ecommerce.layout.app>
    @slot('styles')
    <style>
        /* ===== Shared motion ===== */
        .qb-reveal {
            opacity: 0;
            transform: translateY(26px);
            transition: opacity .6s cubic-bezier(.22,.61,.36,1), transform .6s cubic-bezier(.22,.61,.36,1);
            transition-delay: calc(var(--qb-stagger, 0) * 70ms);
        }
        .qb-reveal.is-visible { opacity: 1; transform: none; }

        @keyframes qb-float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-18px) rotate(3deg); }
        }
        @keyframes qb-pop-in {
            from { opacity: 0; transform: translateY(20px) scale(.97); }
            to { opacity: 1; transform: none; }
        }
        @keyframes qb-dot-progress {
            from { width: 0; }
            to { width: 100%; }
        }

        /* ===== Hero Carousel ===== */
        .qb-carousel {
            position: relative;
            overflow: hidden;
            border-radius: 24px;
            box-shadow: 0 16px 50px rgba(var(--qb-primary-dark-rgb, 217, 104, 74), 0.22);
            touch-action: pan-y;
        }
        .qb-carousel-inner {
            display: flex;
            transition: transform .6s cubic-bezier(.22,.61,.36,1);
        }
        .qb-carousel-inner.is-dragging { transition: none; cursor: grabbing; }
        .qb-carousel-slide { min-width: 100%; position: relative; }
        .qb-carousel-slide img,
        .qb-carousel-slide video {
            width: 100%;
            height: 440px;
            object-fit: cover;
            display: block;
            pointer-events: none;
            user-select: none;
        }
        .qb-carousel-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(10,10,20,0.68) 0%, rgba(10,10,20,0.28) 55%, transparent 100%);
            display: flex;
            align-items: center;
            padding: 3rem 4.5rem;
        }
        .qb-carousel-content { color: #fff; max-width: 55%; }
        .qb-carousel-content > * {
            opacity: 0;
            transform: translateY(20px);
        }
        .qb-carousel-slide.active .qb-carousel-content > * {
            animation: qb-pop-in .7s cubic-bezier(.22,.61,.36,1) forwards;
        }
        .qb-carousel-slide.active .qb-carousel-content > *:nth-child(2) { animation-delay: .12s; }
        .qb-carousel-slide.active .qb-carousel-content > *:nth-child(3) { animation-delay: .24s; }
        .qb-carousel-content h2 {
            font-size: 2.75rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 1rem;
            line-height: 1.15;
        }
        .qb-carousel-content p {
            font-size: 1.15rem;
            margin-bottom: 1.75rem;
            opacity: .92;
            line-height: 1.6;
        }
        .qb-carousel-btn {
            background: var(--qb-primary);
            color: #fff;
            border: none;
            padding: 14px 32px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform .3s, box-shadow .3s, background .3s;
            box-shadow: 0 4px 15px rgba(var(--qb-primary-rgb, 255, 140, 105), 0.4);
        }
        .qb-carousel-btn:hover {
            background: var(--qb-primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(var(--qb-primary-rgb, 255, 140, 105), 0.5);
            color: #fff;
        }
        .qb-carousel-dots {
            position: absolute;
            bottom: 22px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 10;
        }
        .qb-carousel-dot {
            position: relative;
            width: 12px;
            height: 6px;
            border-radius: 50px;
            background: rgba(255,255,255,0.45);
            border: none;
            padding: 0;
            cursor: pointer;
            overflow: hidden;
            transition: width .35s cubic-bezier(.22,.61,.36,1), background .35s;
        }
        .qb-carousel-dot.active { width: 44px; background: rgba(255,255,255,0.35); }
        .qb-carousel-dot.active::after {
            content: '';
            position: absolute;
            inset: 0 auto 0 0;
            background: #fff;
            border-radius: 50px;
            animation: qb-dot-progress var(--qb-autoplay, 6s) linear forwards;
        }
        .qb-carousel.is-paused .qb-carousel-dot.active::after { animation-play-state: paused; }
        .qb-carousel-arrows {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 0 20px;
            pointer-events: none;
            z-index: 10;
        }
        .qb-carousel-arrow {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(255,255,255,0.18);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            pointer-events: auto;
            transition: background .3s, transform .3s;
            color: #fff;
        }
        .qb-carousel-arrow:hover { background: rgba(255,255,255,0.95); transform: scale(1.08); }
        .qb-carousel-arrow:hover i { color: var(--qb-primary-dark) !important; }

        /* ===== Default Hero ===== */
        .qb-hero-default {
            background: linear-gradient(130deg, var(--qb-primary) 0%, var(--qb-primary-dark) 55%, var(--qb-accent) 100%);
            border-radius: 24px;
            padding: 4.5rem 4rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 16px 50px rgba(var(--qb-primary-dark-rgb, 217, 104, 74), 0.3);
        }
        .qb-hero-blob {
            position: absolute;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.14) 0%, transparent 70%);
            animation: qb-float 9s ease-in-out infinite;
        }
        .qb-hero-blob.b1 { width: 560px; height: 560px; top: -45%; right: -12%; }
        .qb-hero-blob.b2 { width: 380px; height: 380px; bottom: -35%; left: 28%; animation-delay: -3s; }
        .qb-hero-blob.b3 { width: 220px; height: 220px; top: 10%; left: -6%; animation-delay: -6s; }
        .qb-hero-emoji {
            position: absolute;
            font-size: 2.6rem;
            opacity: .35;
            animation: qb-float 7s ease-in-out infinite;
            filter: drop-shadow(0 6px 12px rgba(0,0,0,.18));
            pointer-events: none;
        }
        .qb-hero-content { position: relative; z-index: 1; max-width: 640px; }
        .qb-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.3);
            color: #fff;
            padding: 8px 18px;
            border-radius: 50px;
            font-size: .85rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .qb-hero-title {
            font-size: 3.4rem;
            font-weight: 800;
            color: #fff;
            line-height: 1.1;
            margin-bottom: 1.25rem;
        }
        .qb-hero-title .qb-underline {
            position: relative;
            white-space: nowrap;
        }
        .qb-hero-title .qb-underline::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: 6px;
            height: 12px;
            background: rgba(255,255,255,0.3);
            border-radius: 6px;
            z-index: -1;
        }
        .qb-hero-subtitle {
            font-size: 1.2rem;
            color: rgba(255,255,255,0.92);
            margin-bottom: 1.75rem;
            max-width: 520px;
            line-height: 1.6;
        }
        .qb-hero-search {
            display: flex;
            max-width: 520px;
            background: #fff;
            border-radius: 50px;
            padding: 6px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.18);
            margin-bottom: 1.75rem;
        }
        .qb-hero-search input {
            flex: 1;
            border: none;
            outline: none;
            background: transparent;
            padding: 10px 20px;
            font-size: 1rem;
            color: #1a1a2e;
            min-width: 0;
        }
        .qb-hero-search button {
            background: var(--qb-primary);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 10px 26px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background .3s, transform .2s;
            white-space: nowrap;
        }
        .qb-hero-search button:hover { background: var(--qb-primary-dark); transform: scale(1.03); }
        .qb-hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 2.25rem; }
        .qb-hero-btn {
            background: #fff;
            color: var(--qb-primary-dark);
            padding: 14px 32px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.05rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: transform .3s, box-shadow .3s;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .qb-hero-btn:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(0,0,0,0.2); color: var(--qb-primary-dark); }
        .qb-hero-btn.ghost {
            background: transparent;
            color: #fff;
            border: 2px solid rgba(255,255,255,0.6);
            box-shadow: none;
        }
        .qb-hero-btn.ghost:hover { background: rgba(255,255,255,0.14); color: #fff; }
        .qb-hero-stats { display: flex; flex-wrap: wrap; gap: 2.5rem; }
        .qb-hero-stat .num { font-size: 1.7rem; font-weight: 800; color: #fff; line-height: 1; }
        .qb-hero-stat .lbl { font-size: .85rem; color: rgba(255,255,255,0.8); margin-top: 4px; }

        /* ===== Perks strip ===== */
        .qb-perks {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        .qb-perk {
            background: #fff;
            border-radius: 16px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 8px 28px rgba(0,0,0,0.08);
            transition: transform .3s, box-shadow .3s;
        }
        .qb-perk:hover { transform: translateY(-4px); box-shadow: 0 14px 34px rgba(0,0,0,0.12); }
        .qb-perk-icon {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            border-radius: 12px;
            background: rgba(var(--qb-primary-rgb, 255, 140, 105), 0.14);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .qb-perk b { font-size: .95rem; color: #1a1a2e; display: block; }
        .qb-perk span { font-size: .8rem; color: #888; }

        /* ===== Section headers ===== */
        .qb-section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 1.75rem;
            gap: 1rem;
        }
        .qb-section-eyebrow {
            text-transform: uppercase;
            letter-spacing: .12em;
            font-size: .78rem;
            font-weight: 700;
            color: var(--qb-primary);
            margin-bottom: 6px;
        }
        .qb-section-title {
            font-size: 1.85rem;
            font-weight: 800;
            color: #1a1a2e;
            margin: 0;
            line-height: 1.2;
        }
        .qb-section-link {
            color: var(--qb-primary);
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            transition: gap .3s, color .3s;
        }
        .qb-section-link:hover { gap: 10px; color: var(--qb-primary-dark); }

        /* ===== Featured rail ===== */
        .qb-rail-wrap { position: relative; }
        /* The rail is a scroll container, so it clips vertically as well as
           horizontally. Oversized padding (offset by negative margins, which
           keeps the rendered layout identical) gives the cards' hover lift,
           badge, and shadow room to breathe instead of shearing at the top. */
        .qb-rail {
            display: flex;
            gap: 1.1rem;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            scroll-padding-left: 16px;
            padding: 16px 16px 30px;
            margin: -12px -12px -16px;
            scrollbar-width: none;
        }
        .qb-rail::-webkit-scrollbar { display: none; }
        .qb-product-card {
            flex: 0 0 215px;
            scroll-snap-align: start;
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
            transition: transform .35s cubic-bezier(.22,.61,.36,1), box-shadow .35s;
        }
        .qb-product-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 36px rgba(var(--qb-primary-rgb, 255, 140, 105), 0.18);
        }
        .qb-product-media {
            position: relative;
            background: #f7f7f9;
            aspect-ratio: 1 / 1;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .qb-product-media img {
            max-width: 78%;
            max-height: 78%;
            object-fit: contain;
            transition: transform .45s cubic-bezier(.22,.61,.36,1);
        }
        .qb-product-card:hover .qb-product-media img { transform: scale(1.08); }
        .qb-product-media .qb-img-placeholder span { transition: transform .45s cubic-bezier(.22,.61,.36,1); }
        .qb-product-card:hover .qb-product-media .qb-img-placeholder span { transform: scale(1.12); }
        .qb-product-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: linear-gradient(135deg, var(--qb-primary), var(--qb-accent));
            color: #fff;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 50px;
            box-shadow: 0 4px 12px rgba(var(--qb-primary-rgb, 255, 140, 105), 0.4);
        }
        .qb-product-body { padding: .9rem 1rem 1rem; display: flex; flex-direction: column; flex: 1; }
        .qb-product-name {
            font-weight: 600;
            color: #1a1a2e;
            font-size: .92rem;
            line-height: 1.35;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 2.5em;
            margin-bottom: .35rem;
        }
        .qb-product-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-top: auto;
        }
        .qb-product-price { font-size: 1.1rem; }
        .qb-rail-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 5;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: none;
            background: #fff;
            color: var(--qb-primary-dark);
            box-shadow: 0 6px 20px rgba(0,0,0,0.14);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform .25s, opacity .25s, box-shadow .25s;
        }
        .qb-rail-arrow:hover { transform: translateY(-50%) scale(1.1); box-shadow: 0 10px 26px rgba(0,0,0,0.18); }
        .qb-rail-arrow.prev { left: -14px; }
        .qb-rail-arrow.next { right: -14px; }
        .qb-rail-arrow[disabled] { opacity: 0; pointer-events: none; }

        /* ===== Category grid ===== */
        .qb-category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 1.1rem;
        }
        .qb-category-item {
            position: relative;
            background: #fff;
            border-radius: 18px;
            padding: 1.6rem 1.25rem 1.4rem;
            text-align: center;
            text-decoration: none;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            overflow: hidden;
            transition: transform .35s cubic-bezier(.22,.61,.36,1), box-shadow .35s, border-color .35s;
        }
        .qb-category-item::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(160deg, rgba(var(--qb-primary-rgb, 255, 140, 105), 0.10), transparent 55%);
            opacity: 0;
            transition: opacity .35s;
        }
        .qb-category-item:hover {
            transform: translateY(-6px);
            border-color: rgba(var(--qb-primary-rgb, 255, 140, 105), 0.55);
            box-shadow: 0 14px 32px rgba(var(--qb-primary-rgb, 255, 140, 105), 0.16);
        }
        .qb-category-item:hover::before { opacity: 1; }
        .qb-category-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, rgba(var(--qb-primary-rgb, 255, 140, 105), 0.16), rgba(var(--qb-primary-dark-rgb, 217, 104, 74), 0.16));
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.8rem;
            position: relative;
            transition: transform .35s cubic-bezier(.34,1.56,.64,1);
        }
        .qb-category-item:hover .qb-category-icon { transform: scale(1.12) rotate(-4deg); }
        .qb-category-name { font-weight: 700; color: #1a1a2e; font-size: 1rem; position: relative; }
        .qb-category-count { font-size: .8rem; color: #999; margin-top: 4px; position: relative; }
        .qb-category-go {
            position: absolute;
            right: 12px;
            top: 12px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(var(--qb-primary-rgb, 255, 140, 105), 0.14);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transform: translateX(-6px);
            transition: opacity .3s, transform .3s;
        }
        .qb-category-item:hover .qb-category-go { opacity: 1; transform: none; }

        /* ===== How it works ===== */
        .qb-steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }
        .qb-step {
            position: relative;
            background: #fff;
            border-radius: 18px;
            padding: 2rem 1.75rem;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            transition: transform .35s, box-shadow .35s;
        }
        .qb-step:hover { transform: translateY(-5px); box-shadow: 0 12px 30px rgba(0,0,0,0.08); }
        .qb-step-num {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            font-size: 2.6rem;
            font-weight: 800;
            line-height: 1;
            color: rgba(var(--qb-primary-rgb, 255, 140, 105), 0.18);
        }
        .qb-step-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--qb-primary), var(--qb-primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.1rem;
            box-shadow: 0 8px 20px rgba(var(--qb-primary-rgb, 255, 140, 105), 0.35);
        }
        .qb-step-title { font-size: 1.1rem; font-weight: 700; color: #1a1a2e; margin-bottom: .4rem; }
        .qb-step-desc { color: #666; font-size: .92rem; line-height: 1.55; margin: 0; }

        /* ===== Why choose us ===== */
        .qb-features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
        }
        .qb-feature-card {
            background: #fff;
            border-radius: 18px;
            padding: 2rem 1.5rem;
            text-align: center;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            transition: transform .35s, box-shadow .35s;
        }
        .qb-feature-card:hover { transform: translateY(-6px); box-shadow: 0 14px 32px rgba(0,0,0,0.09); }
        .qb-feature-icon {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, var(--qb-primary), var(--qb-primary-dark));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.2rem;
            color: #fff;
            box-shadow: 0 10px 24px rgba(var(--qb-primary-rgb, 255, 140, 105), 0.35);
            transition: transform .35s cubic-bezier(.34,1.56,.64,1);
        }
        .qb-feature-card:hover .qb-feature-icon { transform: scale(1.1) rotate(-5deg); }
        .qb-feature-title { font-size: 1.1rem; font-weight: 700; color: #1a1a2e; margin-bottom: .5rem; }
        .qb-feature-desc { color: #666; font-size: .92rem; line-height: 1.5; margin: 0; }

        /* ===== CTA banner ===== */
        .qb-cta {
            position: relative;
            overflow: hidden;
            border-radius: 24px;
            background: linear-gradient(120deg, var(--qb-primary-dark), var(--qb-accent));
            padding: 3.5rem 3rem;
            text-align: center;
            box-shadow: 0 16px 44px rgba(var(--qb-primary-dark-rgb, 217, 104, 74), 0.28);
        }
        .qb-cta h2 { color: #fff; font-weight: 800; font-size: 2.1rem; margin-bottom: .75rem; position: relative; }
        .qb-cta p { color: rgba(255,255,255,0.9); font-size: 1.05rem; margin-bottom: 1.75rem; position: relative; }

        /* ===== Responsive ===== */
        @media (max-width: 992px) {
            .qb-hero-title { font-size: 2.5rem; }
            .qb-hero-default { padding: 3rem 2rem; }
            .qb-hero-stats { gap: 1.5rem; }
            .qb-carousel-slide img, .qb-carousel-slide video { height: 360px; }
            .qb-carousel-content { max-width: 75%; }
            .qb-carousel-content h2 { font-size: 2rem; }
            .qb-steps { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .qb-hero-title { font-size: 1.9rem; }
            .qb-hero-subtitle { font-size: 1rem; }
            .qb-hero-emoji { display: none; }
            .qb-carousel-slide img, .qb-carousel-slide video { height: 300px; }
            .qb-carousel-content { max-width: 92%; }
            .qb-carousel-content h2 { font-size: 1.5rem; }
            .qb-carousel-overlay { padding: 1.5rem 1.75rem; }
            .qb-carousel-arrows { display: none; }
            .qb-section-title { font-size: 1.4rem; }
            .qb-product-card { flex-basis: 175px; }
            .qb-category-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
            .qb-rail-arrow { display: none; }
            .qb-cta { padding: 2.5rem 1.5rem; }
            .qb-cta h2 { font-size: 1.5rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            .qb-reveal { opacity: 1; transform: none; transition: none; }
            .qb-hero-blob, .qb-hero-emoji, .qb-carousel-slide.active .qb-carousel-content > * { animation: none; }
            .qb-carousel-slide .qb-carousel-content > * { opacity: 1; transform: none; }
            .qb-carousel-inner { transition: none; }
        }
    </style>
    @endslot
    @slot('search') @endslot

    <div class="container mb-10">
        @if($announcements->count() > 0)
        <!--begin::Announcement Carousel-->
        <div class="qb-carousel mb-6" id="announcementCarousel" tabindex="0" aria-roledescription="carousel" style="--qb-autoplay: 6s;">
            <div class="qb-carousel-inner" id="carouselInner">
                @foreach($announcements as $announcement)
                <div class="qb-carousel-slide {{ $loop->first ? 'active' : '' }}">
                    @if($announcement->isVideo())
                        <video autoplay muted loop playsinline>
                            <source src="{{ $announcement->media_url }}" type="video/mp4">
                        </video>
                    @else
                        <img src="{{ $announcement->media_url }}" alt="{{ $announcement->title }}" draggable="false">
                    @endif
                    <div class="qb-carousel-overlay">
                        <div class="qb-carousel-content">
                            <h2>{{ $announcement->title }}</h2>
                            @if($announcement->description)
                                <p>{{ $announcement->description }}</p>
                            @endif
                            @if($announcement->link_url)
                                <a href="{{ $announcement->link_url }}" class="qb-carousel-btn">
                                    {{ $announcement->link_text ?: 'Learn More' }}
                                    <i class="ki-duotone ki-arrow-right fs-4"><span class="path1"></span><span class="path2"></span></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            @if($announcements->count() > 1)
            <div class="qb-carousel-arrows">
                <button class="qb-carousel-arrow" id="prevSlide" aria-label="Previous slide">
                    <i class="ki-duotone ki-arrow-left fs-2 text-white"><span class="path1"></span><span class="path2"></span></i>
                </button>
                <button class="qb-carousel-arrow" id="nextSlide" aria-label="Next slide">
                    <i class="ki-duotone ki-arrow-right fs-2 text-white"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>
            <div class="qb-carousel-dots" id="carouselDots">
                @foreach($announcements as $index => $announcement)
                    <button class="qb-carousel-dot {{ $index === 0 ? 'active' : '' }}" data-slide="{{ $index }}" aria-label="Go to slide {{ $index + 1 }}"></button>
                @endforeach
            </div>
            @endif
        </div>
        <!--end::Announcement Carousel-->
        @else
        <!--begin::Default Hero Banner-->
        <div class="qb-hero-default mb-6">
            <span class="qb-hero-blob b1"></span>
            <span class="qb-hero-blob b2"></span>
            <span class="qb-hero-blob b3"></span>
            <span class="qb-hero-emoji" style="top: 16%; right: 10%;">🥦</span>
            <span class="qb-hero-emoji" style="top: 52%; right: 22%; animation-delay: -2s;">🍞</span>
            <span class="qb-hero-emoji" style="top: 76%; right: 8%; animation-delay: -4s;">🧺</span>
            <span class="qb-hero-emoji" style="top: 30%; right: 30%; animation-delay: -5s; font-size: 2rem;">🥛</span>
            <div class="qb-hero-content">
                <div class="qb-hero-badge">
                    <i class="ki-duotone ki-rocket fs-5 text-white"><span class="path1"></span><span class="path2"></span></i>
                    Free Pickup Available
                </div>
                <h1 class="qb-hero-title">Fresh Groceries,<br><span class="qb-underline">Made Easy</span></h1>
                <p class="qb-hero-subtitle">Browse {{ $branding['brand_name'] ?? 'Quick Baskets' }}' full range online — order in minutes, then pick up in-store or have it delivered.</p>
                <form class="qb-hero-search" action="{{ route('shops.products.index') }}" method="GET" role="search">
                    <input type="text" name="query" placeholder="Search for rice, snacks, soap..." aria-label="Search products">
                    <button type="submit">
                        <i class="ki-duotone ki-magnifier fs-3 text-white"><span class="path1"></span><span class="path2"></span></i>
                        Search
                    </button>
                </form>
                <div class="qb-hero-actions">
                    <a href="{{ route('shops.products.index') }}" class="qb-hero-btn">
                        Start Shopping
                        <i class="ki-duotone ki-arrow-right fs-3"><span class="path1"></span><span class="path2"></span></i>
                    </a>
                    <a href="#shopCategories" class="qb-hero-btn ghost">Browse Categories</a>
                </div>
                <div class="qb-hero-stats">
                    <div class="qb-hero-stat">
                        <div class="num">{{ number_format($productCount) }}+</div>
                        <div class="lbl">Products</div>
                    </div>
                    <div class="qb-hero-stat">
                        <div class="num">{{ number_format($categoryCount) }}</div>
                        <div class="lbl">Categories</div>
                    </div>
                    <div class="qb-hero-stat">
                        <div class="num">Daily</div>
                        <div class="lbl">Fresh Restocks</div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Default Hero Banner-->
        @endif

        <!--begin::Perks strip-->
        <div class="qb-perks mb-10">
            <div class="qb-perk qb-reveal" style="--qb-stagger: 0">
                <div class="qb-perk-icon"><i class="ki-duotone ki-delivery-time fs-2 qb-icon"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i></div>
                <div><b>Same-day Pickup</b><span>Ready within hours</span></div>
            </div>
            <div class="qb-perk qb-reveal" style="--qb-stagger: 1">
                <div class="qb-perk-icon"><i class="ki-duotone ki-shield-tick fs-2 qb-icon"><span class="path1"></span><span class="path2"></span></i></div>
                <div><b>Quality Guaranteed</b><span>Fresh, every time</span></div>
            </div>
            <div class="qb-perk qb-reveal" style="--qb-stagger: 2">
                <div class="qb-perk-icon"><i class="ki-duotone ki-wallet fs-2 qb-icon"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i></div>
                <div><b>Best Prices</b><span>Deals all year round</span></div>
            </div>
            <div class="qb-perk qb-reveal" style="--qb-stagger: 3">
                <div class="qb-perk-icon"><i class="ki-duotone ki-heart fs-2 qb-icon"><span class="path1"></span><span class="path2"></span></i></div>
                <div><b>Loyalty Points</b><span>Earn on every order</span></div>
            </div>
        </div>
        <!--end::Perks strip-->

        @if($featuredItems->isNotEmpty())
        <!--begin::Featured Products Section-->
        <div class="mb-12">
            <div class="qb-section-header qb-reveal">
                <div>
                    <div class="qb-section-eyebrow">Handpicked for you</div>
                    <h2 class="qb-section-title">Featured Products</h2>
                </div>
                <a href="{{ route('shops.products.index') }}" class="qb-section-link">
                    View All
                    <i class="ki-duotone ki-arrow-right fs-5"><span class="path1"></span><span class="path2"></span></i>
                </a>
            </div>
            <div class="qb-rail-wrap">
                <button type="button" class="qb-rail-arrow prev" id="railPrev" aria-label="Scroll featured products left" disabled>
                    <i class="ki-duotone ki-arrow-left fs-2"><span class="path1"></span><span class="path2"></span></i>
                </button>
                <div class="qb-rail" id="featuredRail">
                    @foreach($featuredItems as $featuredItem)
                        @php($displayPrice = round($featuredItem->price == 0 ? $featuredItem->cost + ($featuredItem->cost * ($featuredItem->markup / 100)) : $featuredItem->price, 2))
                        <div class="qb-product-card qb-reveal" style="--qb-stagger: {{ $loop->index % 6 }}">
                            {{-- Link wraps media + name only — price row holds the Livewire
                                 cart button, and a nested <button> inside <a> is invalid HTML. --}}
                            <a href="{{ route('shops.products.show', $featuredItem->id) }}" class="text-decoration-none">
                                <div class="qb-product-media">
                                    <span class="qb-product-badge">Featured</span>
                                    <x-ecommerce.product-image :item="$featuredItem" emoji-class="fs-4x" loading="lazy" />
                                </div>
                                <div class="qb-product-body pb-0">
                                    <div class="qb-product-name">{{ $featuredItem->name }}</div>
                                </div>
                            </a>
                            <div class="qb-product-body pt-0">
                                <div class="qb-product-foot">
                                    <span class="qb-price qb-product-price">₱{{ number_format($displayPrice, 2) }}</span>
                                    <livewire:ecommerce.add-to-cart-button :item-id="$featuredItem->id" :wire:key="'home-cart-btn-'.$featuredItem->id" />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="qb-rail-arrow next" id="railNext" aria-label="Scroll featured products right">
                    <i class="ki-duotone ki-arrow-right fs-2"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>
        </div>
        <!--end::Featured Products Section-->
        @endif

        <!--begin::Categories Section-->
        <div class="mb-12" id="shopCategories">
            <div class="qb-section-header qb-reveal">
                <div>
                    <div class="qb-section-eyebrow">Find it fast</div>
                    <h2 class="qb-section-title">Shop by Category</h2>
                </div>
                <a href="{{ route('shops.products.index') }}" class="qb-section-link">
                    View All
                    <i class="ki-duotone ki-arrow-right fs-5"><span class="path1"></span><span class="path2"></span></i>
                </a>
            </div>
            <div class="qb-category-grid">
                @foreach($categories as $category)
                    <a href="{{ route('shops.products.index', ['category' => $category->id]) }}" class="qb-category-item qb-reveal" style="--qb-stagger: {{ $loop->index % 6 }}">
                        <div class="qb-category-icon">{{ $category->icon ?: '🛒' }}</div>
                        <div class="qb-category-name">{{ $category->name }}</div>
                        <div class="qb-category-count">{{ $category->items_count }} items</div>
                        <span class="qb-category-go">
                            <i class="ki-duotone ki-arrow-right fs-6 qb-icon"><span class="path1"></span><span class="path2"></span></i>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
        <!--end::Categories Section-->

        <!--begin::How It Works-->
        <div class="mb-12">
            <div class="qb-section-header qb-reveal">
                <div>
                    <div class="qb-section-eyebrow">Simple as 1-2-3</div>
                    <h2 class="qb-section-title">How It Works</h2>
                </div>
            </div>
            <div class="qb-steps">
                <div class="qb-step qb-reveal" style="--qb-stagger: 0">
                    <span class="qb-step-num">1</span>
                    <div class="qb-step-icon">
                        <i class="ki-duotone ki-magnifier fs-2x text-white"><span class="path1"></span><span class="path2"></span></i>
                    </div>
                    <h3 class="qb-step-title">Browse &amp; Add</h3>
                    <p class="qb-step-desc">Explore the catalog, search what you need, and fill your basket in a few taps.</p>
                </div>
                <div class="qb-step qb-reveal" style="--qb-stagger: 1">
                    <span class="qb-step-num">2</span>
                    <div class="qb-step-icon">
                        <i class="ki-duotone ki-credit-cart fs-2x text-white"><span class="path1"></span><span class="path2"></span></i>
                    </div>
                    <h3 class="qb-step-title">Order &amp; Pay</h3>
                    <p class="qb-step-desc">Check out securely online — we'll confirm your order right away.</p>
                </div>
                <div class="qb-step qb-reveal" style="--qb-stagger: 2">
                    <span class="qb-step-num">3</span>
                    <div class="qb-step-icon">
                        <i class="ki-outline ki-handcart fs-2x text-white"></i>
                    </div>
                    <h3 class="qb-step-title">Pick Up or Receive</h3>
                    <p class="qb-step-desc">Grab your order in-store at your convenience, or have it brought to your door.</p>
                </div>
            </div>
        </div>
        <!--end::How It Works-->

        <!--begin::Why Choose Us-->
        <div class="mb-12">
            <div class="qb-section-header qb-reveal">
                <div>
                    <div class="qb-section-eyebrow">Our promise</div>
                    <h2 class="qb-section-title">Why Choose {{ $branding['brand_name'] ?? 'Quick Baskets' }}?</h2>
                </div>
            </div>
            <div class="qb-features-grid">
                <div class="qb-feature-card qb-reveal" style="--qb-stagger: 0">
                    <div class="qb-feature-icon">
                        <i class="ki-duotone ki-delivery-time text-white fs-2x"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                    </div>
                    <h3 class="qb-feature-title">Fast Pickup</h3>
                    <p class="qb-feature-desc">Order online and pick up your groceries in-store within hours.</p>
                </div>
                <div class="qb-feature-card qb-reveal" style="--qb-stagger: 1">
                    <div class="qb-feature-icon">
                        <i class="ki-duotone ki-shield-tick text-white fs-2x"><span class="path1"></span><span class="path2"></span></i>
                    </div>
                    <h3 class="qb-feature-title">Quality Guaranteed</h3>
                    <p class="qb-feature-desc">We source only the freshest products for our customers.</p>
                </div>
                <div class="qb-feature-card qb-reveal" style="--qb-stagger: 2">
                    <div class="qb-feature-icon">
                        <i class="ki-duotone ki-wallet text-white fs-2x"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                    </div>
                    <h3 class="qb-feature-title">Best Prices</h3>
                    <p class="qb-feature-desc">Competitive pricing with regular discounts and promotions.</p>
                </div>
                <div class="qb-feature-card qb-reveal" style="--qb-stagger: 3">
                    <div class="qb-feature-icon">
                        <i class="ki-duotone ki-like text-white fs-2x"><span class="path1"></span><span class="path2"></span></i>
                    </div>
                    <h3 class="qb-feature-title">Easy Shopping</h3>
                    <p class="qb-feature-desc">Browse, order, and pay with our user-friendly platform.</p>
                </div>
            </div>
        </div>
        <!--end::Why Choose Us-->

        <!--begin::CTA banner-->
        <div class="qb-cta qb-reveal mb-5">
            <span class="qb-hero-blob b1"></span>
            <span class="qb-hero-blob b3"></span>
            <h2>Ready to fill your basket?</h2>
            <p>Hundreds of everyday essentials, one quick checkout away.</p>
            <a href="{{ route('shops.products.index') }}" class="qb-hero-btn">
                Shop Now
                <i class="ki-duotone ki-arrow-right fs-3"><span class="path1"></span><span class="path2"></span></i>
            </a>
        </div>
        <!--end::CTA banner-->
    </div>

    @slot('scripts')
    <script>
    (function() {
        'use strict';
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        /* ----- Reveal on scroll ----- */
        const reveals = document.querySelectorAll('.qb-reveal');
        if (!reducedMotion && 'IntersectionObserver' in window) {
            const io = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        io.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
            reveals.forEach(function(el) { io.observe(el); });
        } else {
            reveals.forEach(function(el) { el.classList.add('is-visible'); });
        }

        /* ----- Featured products rail ----- */
        const rail = document.getElementById('featuredRail');
        const railPrev = document.getElementById('railPrev');
        const railNext = document.getElementById('railNext');
        if (rail && railPrev && railNext) {
            const step = function() { return Math.max(rail.clientWidth * 0.8, 240); };
            const sync = function() {
                railPrev.disabled = rail.scrollLeft <= 4;
                railNext.disabled = rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 4;
            };
            railPrev.addEventListener('click', function() { rail.scrollBy({ left: -step(), behavior: 'smooth' }); });
            railNext.addEventListener('click', function() { rail.scrollBy({ left: step(), behavior: 'smooth' }); });
            rail.addEventListener('scroll', sync, { passive: true });
            window.addEventListener('resize', sync);
            sync();
        }

        /* ----- Announcement carousel ----- */
        const carousel = document.getElementById('announcementCarousel');
        if (!carousel) return;
        const inner = document.getElementById('carouselInner');
        const slides = inner.querySelectorAll('.qb-carousel-slide');
        const dots = document.querySelectorAll('.qb-carousel-dot');
        const prevBtn = document.getElementById('prevSlide');
        const nextBtn = document.getElementById('nextSlide');
        const totalSlides = slides.length;
        if (totalSlides <= 1) return;

        const AUTOPLAY_MS = 6000;
        let currentSlide = 0;
        let autoplayInterval = null;

        function goToSlide(index) {
            if (index < 0) index = totalSlides - 1;
            if (index >= totalSlides) index = 0;
            currentSlide = index;
            inner.style.transform = 'translateX(-' + (currentSlide * 100) + '%)';
            slides.forEach(function(slide, i) { slide.classList.toggle('active', i === currentSlide); });
            dots.forEach(function(dot, i) {
                dot.classList.remove('active');
                if (i === currentSlide) {
                    // Reflow so the progress-fill animation restarts.
                    void dot.offsetWidth;
                    dot.classList.add('active');
                }
            });
        }

        function nextSlide() { goToSlide(currentSlide + 1); }
        function prevSlide() { goToSlide(currentSlide - 1); }
        function startAutoplay() {
            stopAutoplay();
            carousel.classList.remove('is-paused');
            autoplayInterval = setInterval(nextSlide, AUTOPLAY_MS);
        }
        function stopAutoplay() {
            carousel.classList.add('is-paused');
            if (autoplayInterval) clearInterval(autoplayInterval);
            autoplayInterval = null;
        }
        function restartAutoplay() { stopAutoplay(); startAutoplay(); }

        if (prevBtn) prevBtn.addEventListener('click', function() { prevSlide(); restartAutoplay(); });
        if (nextBtn) nextBtn.addEventListener('click', function() { nextSlide(); restartAutoplay(); });
        dots.forEach(function(dot, index) {
            dot.addEventListener('click', function() { goToSlide(index); restartAutoplay(); });
        });

        carousel.addEventListener('mouseenter', stopAutoplay);
        carousel.addEventListener('mouseleave', startAutoplay);
        carousel.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft') { prevSlide(); restartAutoplay(); }
            if (e.key === 'ArrowRight') { nextSlide(); restartAutoplay(); }
        });

        // No wasted cycles (or skipped-ahead slides) while the tab is hidden.
        document.addEventListener('visibilitychange', function() {
            document.hidden ? stopAutoplay() : startAutoplay();
        });

        // Swipe / drag.
        let dragStartX = null;
        carousel.addEventListener('pointerdown', function(e) {
            if (e.target.closest('a, button')) return;
            dragStartX = e.clientX;
            stopAutoplay();
        });
        carousel.addEventListener('pointerup', function(e) {
            if (dragStartX === null) return;
            const delta = e.clientX - dragStartX;
            dragStartX = null;
            if (Math.abs(delta) > 50) { delta < 0 ? nextSlide() : prevSlide(); }
            startAutoplay();
        });
        carousel.addEventListener('pointercancel', function() { dragStartX = null; startAutoplay(); });

        startAutoplay();
    })();
    </script>
    @endslot
</x-ecommerce.layout.app>

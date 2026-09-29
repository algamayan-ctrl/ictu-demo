@extends('layouts.app', ['title' => 'Welcome'])

@section('content')
<style>
    .landing-shell {
        --landing-navy: #071b35;
        --landing-blue: #0d63d8;
        --landing-blue-strong: #0758c9;
        --landing-blue-soft: #eef5ff;
        --landing-ink: #0f2545;
        --landing-muted: #60708a;
        --landing-border: #dfe8f4;
        --landing-surface: #ffffff;
        --landing-soft: #f7faff;
        width: 100%;
        color: var(--landing-ink);
        min-height: 100vh;
        background:
            linear-gradient(
                90deg,
                rgba(255,255,255,.98) 0%,
                rgba(255,255,255,.96) 30%,
                rgba(255,255,255,.84) 48%,
                rgba(255,255,255,.42) 66%,
                rgba(255,255,255,.10) 100%
            ),
            url("{{ asset('images/cspc-campus-hero.png') }}") center right / cover no-repeat;
    }

    .landing-shell .spmu-shared-login-card {
        position: relative;
        z-index: 2;
    }

    .landing-shell *,
    .landing-shell *::before,
    .landing-shell *::after {
        box-sizing: border-box;
    }

    .landing-container {
        width: min(1280px, calc(100% - 80px));
        margin-inline: auto;
    }

    .landing-hero {
        position: relative;
        overflow: visible;
        padding: 0;
        border-bottom: 1px solid var(--landing-border);
        background: transparent;
    }

    .landing-hero-grid {
        position: relative;
        width: min(1280px, calc(100% - 80px));
        margin-inline: auto;
        display: block;
        min-height: 500px;
        padding: 72px 0 154px;
    }

    .landing-hero-grid > div:first-child {
        width: min(590px, 100%);
        padding: 0;
    }

    .landing-hero h1 {
        max-width: 620px;
        margin: 0;
        color: var(--landing-navy);
        font-size: clamp(2.45rem, 4.5vw, 4.15rem);
        line-height: .98;
        letter-spacing: -.055em;
        font-weight: 850;
    }

    .landing-hero h1 .accent {
        display: block;
        margin-top: 8px;
        color: var(--landing-blue);
    }

    .landing-hero-copy {
        max-width: 620px;
        margin: 18px 0 0;
        color: var(--landing-muted);
        font-size: .94rem;
        line-height: 1.8;
    }

    .landing-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 22px;
    }

    .landing-actions .button,
    .landing-cta .button {
        min-height: 46px;
        padding-inline: 22px;
        border-radius: 10px;
    }

    .landing-learn-more {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        min-height: 46px;
        padding: 0 20px;
        border: 1px solid #1769e0;
        border-radius: 10px;
        background: rgba(255,255,255,.88);
        color: #1769e0;
        font-weight: 700;
        text-decoration: none;
        box-shadow: 0 8px 20px rgba(23,105,224,.08);
        transition:
            transform .16s ease,
            background-color .16s ease,
            color .16s ease,
            box-shadow .16s ease;
    }

    .landing-learn-more:hover {
        color: #0758c9;
        background: #ffffff;
        box-shadow: 0 10px 24px rgba(23,105,224,.12);
        transform: translateY(-1px);
        text-decoration: none;
    }

    .landing-learn-more:active {
        transform: scale(.98);
    }

    .landing-learn-more__arrow {
        display: inline-block;
        font-size: 1.05rem;
        line-height: 1;
        transition: transform .16s ease;
    }

    .landing-learn-more:hover .landing-learn-more__arrow {
        transform: translateX(3px);
    }




    .landing-icon {
        width: 46px;
        height: 46px;
        display: inline-grid;
        place-items: center;
        border-radius: 50%;
        color: var(--landing-blue);
        background: var(--landing-blue-soft);
        margin-bottom: 12px;
        box-shadow: inset 0 0 0 1px rgba(23,105,224,.04);
    }

    .landing-feature-card h3,
    .landing-process h3 {
        margin: 0;
        color: var(--landing-navy);
        font-size: .95rem;
    }


    .landing-section {
        padding: 76px 0;
    }

    .landing-section.alt {
        background:
            linear-gradient(180deg, rgba(238,245,255,.58), rgba(255,255,255,.35));
        border-block: 1px solid #edf2f8;
    }

    .landing-section-heading {
        max-width: 720px;
        margin: 0 auto 42px;
        text-align: center;
    }

    .landing-section-heading.align-left {
        margin-inline: 0;
        text-align: left;
    }

    .landing-section-heading .mini {
        display: inline-block;
        margin-bottom: 8px;
        color: var(--landing-blue);
        font-size: .78rem;
        font-weight: 800;
    }

    .landing-section-heading h2 {
        margin: 0;
        color: var(--landing-navy);
        font-size: clamp(1.9rem, 3vw, 2.9rem);
        letter-spacing: -.035em;
        line-height: 1.1;
    }

    .landing-section-heading p {
        margin: 14px 0 0;
        color: var(--landing-muted);
        line-height: 1.75;
    }

    .landing-about-section {
        padding-top: 86px;
        padding-bottom: 68px;
    }

    .landing-about-wrap {
        width: min(930px, 100%);
        margin-inline: auto;
    }

    .landing-about-wrap .landing-section-heading {
        max-width: 820px;
        margin-bottom: 22px;
    }

    .landing-about-copy {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 28px;
        padding-top: 22px;
        border-top: 1px solid var(--landing-border);
    }

    .landing-about-copy p {
        margin: 0;
        color: var(--landing-muted);
        font-size: .9rem;
        line-height: 1.8;
    }

    .landing-process {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 18px;
        position: relative;
    }

    .landing-process::before {
        content: "";
        position: absolute;
        top: 32px;
        left: 8%;
        right: 8%;
        border-top: 1px dashed #a9c9f3;
        z-index: 0;
    }

    .landing-process article {
        position: relative;
        z-index: 1;
        text-align: center;
    }

    .landing-process-icon {
        width: 66px;
        height: 66px;
        display: grid;
        place-items: center;
        margin: 0 auto 15px;
        border: 1px solid #cfe0f6;
        border-radius: 50%;
        color: var(--landing-blue);
        background: #fff;
        box-shadow: 0 10px 25px rgba(13, 99, 216, .10);
    }

    .landing-step-no {
        width: 22px;
        height: 22px;
        display: inline-grid;
        place-items: center;
        margin-right: 5px;
        border-radius: 50%;
        color: #fff;
        background: var(--landing-blue);
        font-size: .68rem;
        font-weight: 800;
    }

    .landing-process p {
        margin: 8px auto 0;
        max-width: 150px;
        color: var(--landing-muted);
        font-size: .75rem;
        line-height: 1.55;
    }

    .landing-feature-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }

    .landing-feature-card {
        min-height: 180px;
        padding: 22px;
        border: 1px solid var(--landing-border);
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 10px 26px rgba(13, 54, 100, .045);
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }

    .landing-feature-card:hover {
        transform: translateY(-3px);
        border-color: #bad4f4;
        box-shadow: 0 16px 32px rgba(13, 54, 100, .08);
    }

    .landing-feature-card p {
        margin: 9px 0 0;
        color: var(--landing-muted);
        font-size: .8rem;
        line-height: 1.6;
    }

    .landing-impact {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        overflow: hidden;
        border-radius: 18px;
        background:
            radial-gradient(circle at 90% 10%, rgba(37,118,225,.35), transparent 20rem),
            linear-gradient(135deg, #073566, #071b35 72%);
        box-shadow: 0 22px 45px rgba(7,27,53,.14);
    }

    .landing-impact article {
        position: relative;
        min-height: 150px;
        padding: 28px;
        color: #fff;
    }

    .landing-impact article + article::before {
        content: "";
        position: absolute;
        left: 0;
        top: 24px;
        bottom: 24px;
        width: 1px;
        background: rgba(255,255,255,.14);
    }

    .landing-impact strong {
        display: block;
        margin-top: 10px;
        font-size: 1.15rem;
    }

    .landing-impact p {
        margin: 7px 0 0;
        color: rgba(255,255,255,.70);
        font-size: .76rem;
        line-height: 1.55;
    }

    .landing-cta {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 30px;
        align-items: center;
        padding: 42px 48px;
        border: 1px solid var(--landing-border);
        border-radius: 20px;
        background:
            radial-gradient(circle at 86% 50%, rgba(13,99,216,.12), transparent 18rem),
            linear-gradient(135deg, #ffffff, #f3f8ff);
        box-shadow: 0 14px 34px rgba(13,54,100,.06);
    }

    .landing-cta h2 {
        margin: 0;
        color: var(--landing-navy);
        font-size: clamp(1.5rem, 2.6vw, 2.2rem);
        letter-spacing: -.03em;
    }

    .landing-cta p {
        margin: 10px 0 0;
        color: var(--landing-muted);
    }

    .landing-footer {
        margin-top: 82px;
        background: linear-gradient(135deg, #061a33, #082c57);
        color: rgba(255,255,255,.82);
    }

    .landing-footer-grid {
        display: grid;
        grid-template-columns: 1.4fr .8fr .8fr 1fr;
        gap: 36px;
        padding: 46px 0 36px;
    }

    .landing-footer h3 {
        margin: 0 0 12px;
        color: #fff;
        font-size: .9rem;
    }

    .landing-footer p,
    .landing-footer a {
        color: rgba(255,255,255,.72);
        font-size: .78rem;
        line-height: 1.75;
    }

    .landing-footer a {
        display: block;
        text-decoration: none;
    }

    .landing-footer a:hover {
        color: #fff;
    }

    .landing-footer-brand strong {
        display: block;
        margin-bottom: 7px;
        color: #fff;
        font-size: .94rem;
    }

    .landing-footer-bottom {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        padding: 18px 0 24px;
        border-top: 1px solid rgba(255,255,255,.10);
        font-size: .72rem;
    }

    @media (max-width: 1050px) {
        .landing-hero-grid {
            width: min(100% - 40px, 1180px);
            min-height: 450px;
            padding: 58px 0 126px;
        }


        .landing-process {
            grid-template-columns: repeat(3, 1fr);
            gap: 32px 18px;
        }

        .landing-process::before {
            display: none;
        }

        .landing-feature-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .landing-footer-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 720px) {
        .landing-container {
            width: min(100% - 28px, 1180px);
        }



        .landing-hero h1 {
            font-size: clamp(2.15rem, 11vw, 3.15rem);
        }

        .landing-hero-grid {
            gap: 32px;
            min-height: auto;
        }





        .landing-about-copy,
        .landing-feature-grid,
        .landing-impact,
        .landing-footer-grid,
        .landing-cta {
            grid-template-columns: 1fr;
        }

        .landing-process {
            grid-template-columns: repeat(2, 1fr);
        }

        .landing-impact article + article::before {
            top: 0;
            left: 22px;
            right: 22px;
            width: auto;
            height: 1px;
        }

        .landing-cta {
            padding: 30px 24px;
        }

        .landing-footer-bottom {
            flex-direction: column;
        }
    }

    @media (max-width: 460px) {
        .landing-process {
            grid-template-columns: 1fr;
        }



        .landing-section {
            padding: 64px 0;
        }

    }

    @media (prefers-reduced-motion: reduce) {
        .landing-feature-card {
            transition: none;
        }
    }




/* LANDING EMBEDDED LOGIN START */
/* Reuse existing .login-page card styling without its stand-alone page backdrop. */
.landing-hero .landing-hero-grid {
    display: flex !important;
    align-items: center;
    justify-content: flex-start;
    width: min(1280px, calc(100% - 80px));
    min-height: calc(100vh - 140px);
    min-height: calc(100dvh - 140px);
    padding: 24px 0 34px;
}
.landing-hero .landing-hero-grid > .landing-login-slot.login-page {
    position: relative;
    flex: 0 1 420px;
    width: min(420px, 100%) !important;
    min-width: 0;
    min-height: 0 !important;
    height: auto !important;
    display: block !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: visible !important;
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
}
.landing-hero .landing-login-slot::before,
.landing-hero .landing-login-slot::after {
    content: none !important;
    display: none !important;
}
.landing-hero .landing-login-slot .login-card {
    position: relative;
    z-index: 1;
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 !important;
    padding: 20px 28px 22px !important;
    background: #fff !important;
    border: 1px solid #d7e0eb !important;
    border-radius: 18px !important;
    box-shadow: 0 14px 38px rgba(27,55,91,.12) !important;
}
/* The removed marketing hero h1 rule must not enlarge the login heading. */
.landing-hero .landing-login-slot .login-heading h1 {
    max-width: none;
    font-size: 24px !important;
    font-weight: 750 !important;
    line-height: 1.15 !important;
    letter-spacing: -.025em !important;
}
.landing-hero .landing-login-slot .login-heading p {
    margin-top: 5px;
    font-size: 13px !important;
    line-height: 1.5;
}
@media (max-width: 720px) {
    .landing-hero .landing-hero-grid {
        width: min(100% - 28px, 1280px);
        min-height: 0;
        justify-content: center;
        padding: 18px 0 28px;
    }
    .landing-hero .landing-hero-grid > .landing-login-slot.login-page {
        flex: 0 1 410px;
        width: 100% !important;
    }
    .landing-hero .landing-login-slot .login-card {
        padding: 20px !important;
    }
}
/* LANDING EMBEDDED LOGIN END */

/* LOGIN CARD LOGO OVERRIDE START */

/* SPMU logo */
.login-page .login-card .landing-login-badge.landing-login-badge--logo {
    display: grid !important;
    place-items: start center !important;

    width: 241.5px !important;
    min-width: 0 !important;
    max-width: 241.5px !important;
    /* Preserve the original logo slot so the card and form stay in place. */
    height: calc(210px * 254 / 1626 + 14px) !important;
    min-height: 0 !important;

    margin: 0 auto 14px !important;
    padding: 0 !important;

    border: 0 !important;
    border-radius: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
}

.login-page .login-card .landing-login-badge-logo {
    display: block !important;
    width: 100% !important;
    max-width: 241.5px !important;
    height: auto !important;
    object-fit: contain !important;
}

/* Slightly smaller welcome heading */
.login-page .login-card .login-heading h1 {
    font-size: 22px !important;
    font-weight: 750 !important;
    line-height: 1.2 !important;
    letter-spacing: -0.025em !important;
}

/* Mobile */
@media (max-width: 640px) {

    .login-page .login-card .landing-login-badge.landing-login-badge--logo {
        width: 211.6px !important;
        max-width: 211.6px !important;
        height: calc(184px * 254 / 1626 + 12px) !important;
        margin-bottom: 12px !important;
    }

    .login-page .login-card .landing-login-badge-logo {
        max-width: 211.6px !important;
    }

    .login-page .login-card .login-heading h1 {
        font-size: 21px !important;
    }
}

/* LOGIN CARD LOGO OVERRIDE END */
</style>

<div class="landing-shell">

    {{-- HERO: sign in directly on landing page --}}
    <section class="landing-hero" aria-labelledby="landing-login-title">
        <div class="landing-container landing-hero-grid">
            <div class="landing-login-slot login-page" id="landing-login">
                @include('auth.partials.login-card')
            </div>
        </div>

    </section>




</div>

@endsection

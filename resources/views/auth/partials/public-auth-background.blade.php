<style>
/* UNIVERSAL PUBLIC AUTH BACKGROUND START */

/*
 * Shared visual shell for Sign-in-related public pages.
 * Desktop stays left aligned; mobile remains centered.
 */
.forgot-login-page,
.auth-recovery-page {
    position: relative;

    min-height: 100vh;

    background-image:
        linear-gradient(
            90deg,
            rgba(255,255,255,.98) 0%,
            rgba(255,255,255,.96) 28%,
            rgba(255,255,255,.84) 55%,
            rgba(255,255,255,.42) 78%,
            rgba(255,255,255,.10) 100%
        ),
        url('{{ \App\Support\StaticAsset::url('images/cspc-campus-hero.png') }}');

    background-repeat:
        no-repeat,
        no-repeat;

    background-position:
        center,
        center;

    background-size:
        cover,
        cover;

    background-attachment:
        scroll,
        scroll;
}

/*
 * Keep the card comfortably above the image while allowing
 * the CSPC building to remain visible on the right side.
 */
.forgot-login-card,
.auth-recovery-card {
    position: relative;
    z-index: 1;
}

@media (max-width: 720px) {
    .forgot-login-page,
    .auth-recovery-page {
        background-position:
            center,
            center;

        background-size:
            cover,
            cover;
    }
}

/* UNIVERSAL PUBLIC AUTH BACKGROUND END */
</style>
<style>
/* UNIVERSAL PASSWORD RECOVERY UI START */

/*
 * Forgot Password and Reset Password belong to the same public
 * authentication experience as the normal Sign in page.
 *
 * Hide the generic guest header and global notice because recovery
 * feedback is rendered inside the card itself.
 */
body:has(.auth-recovery-page) .site-header,
body:has(.auth-recovery-page) .app-main > .notice,
body:has(.auth-recovery-page) > footer {
    display: none !important;
}

/*
 * Remove normal application-page spacing so the recovery card occupies
 * the same position as the login card.
 */
body:has(.auth-recovery-page) .app-main {
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    padding: 0 !important;
}

/*
 * Same placement as the existing login card:
 * top-left with a small page margin rather than centered.
 */
.auth-recovery-page {
    width: 100%;
    min-height: 100vh;

    display: flex;
    align-items: flex-start;
    justify-content: flex-start;

    padding: 24px 0 44px 22px;
    box-sizing: border-box;
}

.auth-recovery-card {
    width: min(100%, 420px);
    box-sizing: border-box;

    padding: 24px 28px 26px;

    border: 1px solid #d3dfec;
    border-radius: 18px;

    background: #ffffff;

    box-shadow:
        0 12px 32px rgba(30, 55, 88, .08),
        0 2px 6px rgba(30, 55, 88, .04);
}

.auth-recovery-logo {
    display: block;

    width: min(100%, 245px);
    height: auto;

    margin: 0 auto 22px;
}

.auth-recovery-heading {
    margin: 0;

    color: #08254d;

    font-size: 24px;
    font-weight: 800;
    line-height: 1.15;

    text-align: center;
    letter-spacing: -.02em;
}

.auth-recovery-intro {
    max-width: 340px;

    margin: 8px auto 22px;

    color: #5b7190;

    font-size: 12.5px;
    line-height: 1.55;

    text-align: center;
}

.auth-recovery-status {
    margin: 0 0 18px;
    padding: 12px 13px;

    border: 1px solid #a8dbc1;
    border-radius: 8px;

    background: #eaf8f1;
    color: #12683f;

    font-size: 12px;
    line-height: 1.45;
}

.auth-recovery-form {
    display: grid;
    gap: 16px;
}

.auth-recovery-field {
    display: grid;
    gap: 7px;
}

.auth-recovery-field > label,
.auth-recovery-field > span:first-child {
    color: #08254d;

    font-size: 12.5px;
    font-weight: 800;
}

.auth-recovery-input-wrap {
    position: relative;
}

.auth-recovery-field input {
    width: 100%;
    min-height: 54px;

    box-sizing: border-box;

    padding: 12px 14px;

    border: 1px solid #c7d5e6;
    border-radius: 9px;

    outline: none;

    background: #ffffff;
    color: #132f53;

    font: inherit;

    transition:
        border-color .15s ease,
        box-shadow .15s ease;
}

.auth-recovery-field input:focus {
    border-color: #2879ef;

    box-shadow:
        0 0 0 2px rgba(40, 121, 239, .14);
}

.auth-recovery-field.has-error input {
    border-color: #dc3545;
}

.auth-recovery-error {
    margin: 0;

    color: #dc3545;

    font-size: 11.5px;
    line-height: 1.4;
}

.auth-recovery-help {
    margin: -2px 0 0;

    color: #6c7f99;

    font-size: 11px;
    line-height: 1.4;
}

.auth-recovery-submit {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    width: 100%;
    min-height: 49px;

    margin-top: 2px;
    padding: 10px 18px;

    border: 1px solid #2879ef !important;
    border-radius: 9px;

    background: linear-gradient(
        90deg,
        #205ee8 0%,
        #2e7af0 100%
    ) !important;

    color: #ffffff !important;

    font-size: 12.5px;
    font-weight: 800;

    cursor: pointer;

    box-shadow:
        0 8px 18px rgba(40, 121, 239, .16);

    transition:
        background .15s ease,
        box-shadow .15s ease,
        transform .12s ease;
}

.auth-recovery-submit:hover,
.auth-recovery-submit:focus-visible {
    border-color: #165dd0 !important;
    background: #165dd0 !important;
    color: #ffffff !important;

    box-shadow:
        0 10px 22px rgba(40, 121, 239, .22);
}

.auth-recovery-submit:active {
    transform: translateY(1px);
}

.auth-recovery-back {
    display: flex;
    align-items: center;
    justify-content: center;

    margin-top: 18px;
}

.auth-recovery-back a {
    color: #0969e8;

    font-size: 12px;
    font-weight: 800;

    text-decoration: none;
}

.auth-recovery-back a:hover,
.auth-recovery-back a:focus-visible {
    color: #064eb0;
    text-decoration: underline;
}

.auth-recovery-password-wrap {
    position: relative;
}

.auth-recovery-password-wrap input {
    padding-right: 48px;
}

.auth-recovery-password-toggle {
    position: absolute;

    top: 50%;
    right: 13px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    width: 30px;
    height: 30px;

    padding: 0;

    border: 0 !important;

    background: transparent !important;
    color: #7087a5 !important;

    transform: translateY(-50%);

    cursor: pointer;
    box-shadow: none !important;
}

.auth-recovery-password-toggle:hover {
    color: #2879ef !important;
    background: transparent !important;
}

@media (max-width: 720px) {
    .auth-recovery-page {
        padding: 20px 16px 36px;
    }

    .auth-recovery-card {
        width: 100%;
        max-width: 420px;

        margin: 0 auto;

        padding: 22px 22px 24px;
    }
}

/* UNIVERSAL PASSWORD RECOVERY UI END */
</style>
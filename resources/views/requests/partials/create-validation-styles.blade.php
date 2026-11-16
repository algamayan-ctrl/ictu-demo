<style>

#request-form .create-request-ui {
    gap: 16px;
}

#request-form .create-request-ui .request-picker-actions {
    margin-top: 0 !important;
}

#request-form .create-request-ui .picker-warning {
    margin-top: 12px;
}

#request-form .create-request-ui .button:disabled,
#request-form .create-request-ui .button[disabled] {
    opacity: 1;
    cursor: not-allowed;
    color: var(--text-muted) !important;
    background: var(--surface-subtle) !important;
    border-color: var(--border) !important;
    box-shadow: none !important;
    transform: none !important;
}

#request-form .create-request-ui .button:disabled .ui-icon,
#request-form .create-request-ui .button[disabled] .ui-icon {
    opacity: .65;
}

#request-form .create-request-ui .request-card.review-card,
#request-form .create-request-ui .request-card.documents-card,
#request-form .create-request-ui .request-card.confirmation-card {
    row-gap: 16px;
}

#request-form .create-request-ui .documents-card .document-rows {
    gap: 16px;
}

#request-form .create-request-ui .documents-card .document-row {
    gap: 16px;
    padding: 16px 16px;
}

#request-form .create-request-ui .confirmation-card .final-confirmation {
    gap: 12px;
}

#request-form .create-request-ui .sticky-actions.request-review-actions {
    margin-top: 2px;
}

@media (max-width: 820px) {
    #request-form .create-request-ui {
        gap: 12px;
    }

    #request-form .create-request-ui .documents-card .document-row {
        padding: 12px 12px;
    }
}
</style>

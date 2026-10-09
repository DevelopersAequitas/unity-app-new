import React from 'react';

interface ConfirmModalProps {
    isOpen: boolean;
    title: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'danger' | 'primary' | 'warning' | 'success';
    isLoading?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
}

export const ConfirmModal: React.FC<ConfirmModalProps> = ({
    isOpen,
    title,
    message,
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    variant = 'primary',
    isLoading = false,
    onConfirm,
    onCancel,
}) => {
    if (!isOpen) return null;

    const btnClass = variant === 'danger' ? 'btn-danger' : variant === 'warning' ? 'btn-warning' : variant === 'success' ? 'btn-success' : 'btn-primary';

    return (
        <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0, 0, 0, 0.5)', zIndex: 1060 }}>
            <div className="modal-dialog modal-dialog-centered">
                <div className="modal-content shadow border-0 rounded-4">
                    <div className="modal-header border-bottom-0 pb-0">
                        <h5 className="modal-title fw-bold text-dark">{title}</h5>
                        <button type="button" className="btn-close" disabled={isLoading} onClick={onCancel}></button>
                    </div>
                    <div className="modal-body py-3">
                        <p className="text-secondary mb-0">{message}</p>
                    </div>
                    <div className="modal-footer border-top-0 pt-0">
                        <button type="button" className="btn btn-light rounded-3 px-3" disabled={isLoading} onClick={onCancel}>
                            {cancelText}
                        </button>
                        <button type="button" className={`btn ${btnClass} rounded-3 px-4 fw-semibold`} disabled={isLoading} onClick={onConfirm}>
                            {isLoading ? (
                                <>
                                    <span className="spinner-border spinner-border-sm me-2" role="status"></span>
                                    Processing...
                                </>
                            ) : (
                                confirmText
                            )}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

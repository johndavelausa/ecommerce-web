<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Success Toast (Green accent)
        const SuccessToast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            background: '#FFFFFF',
            color: '#212121',
            iconColor: '#2D9F4E',
            width: '320px',
            padding: '12px 16px',
            customClass: {
                popup: 'rounded-lg shadow-lg mt-20 mr-4 border-l-4 border-l-[#2D9F4E]',
                title: 'text-sm font-medium mt-0 mb-0 text-gray-900',
                icon: '!w-5 !h-5 !border-[2px] !border-[#2D9F4E] !text-[#2D9F4E]',
                timerProgressBar: 'bg-[#2D9F4E] h-[3px]'
            },
            showClass: {
                popup: 'animate-slideInRight'
            },
            hideClass: {
                popup: 'animate-slideOutRight'
            },
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        // Info Toast (Gold accent)
        const InfoToast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            background: '#FFFFFF',
            color: '#212121',
            iconColor: '#F9C74F',
            width: '320px',
            padding: '12px 16px',
            customClass: {
                popup: 'rounded-lg shadow-lg mt-20 mr-4 border-l-4 border-l-[#F9C74F]',
                title: 'text-sm font-medium mt-0 mb-0 text-gray-900',
                icon: '!w-5 !h-5 !border-[2px] !border-[#F9C74F] !text-[#F9C74F]',
                timerProgressBar: 'bg-[#F9C74F] h-[3px]'
            },
            showClass: {
                popup: 'animate-slideInRight'
            },
            hideClass: {
                popup: 'animate-slideOutRight'
            },
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        // Error Toast (Red accent)
        const ErrorToast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            background: '#FFFFFF',
            color: '#212121',
            iconColor: '#EF5350',
            width: '320px',
            padding: '12px 16px',
            customClass: {
                popup: 'rounded-lg shadow-lg mt-20 mr-4 border-l-4 border-l-[#EF5350]',
                title: 'text-sm font-medium mt-0 mb-0 text-gray-900',
                icon: '!w-5 !h-5 !border-[2px] !border-[#EF5350] !text-[#EF5350]',
                timerProgressBar: 'bg-[#EF5350] h-[3px]'
            },
            showClass: {
                popup: 'animate-slideInRight'
            },
            hideClass: {
                popup: 'animate-slideOutRight'
            },
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        // Warning Toast (Orange accent)
        const WarningToast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            background: '#FFFFFF',
            color: '#212121',
            iconColor: '#FFA726',
            width: '320px',
            padding: '12px 16px',
            customClass: {
                popup: 'rounded-lg shadow-lg mt-20 mr-4 border-l-4 border-l-[#FFA726]',
                title: 'text-sm font-medium mt-0 mb-0 text-gray-900',
                icon: '!w-5 !h-5 !border-[2px] !border-[#FFA726] !text-[#FFA726]',
                timerProgressBar: 'bg-[#FFA726] h-[3px]'
            },
            showClass: {
                popup: 'animate-slideInRight'
            },
            hideClass: {
                popup: 'animate-slideOutRight'
            },
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        // Helper function to show toast based on type
        const showToast = (type, message) => {
            switch(type) {
                case 'success':
                    SuccessToast.fire({ icon: 'success', title: message });
                    break;
                case 'error':
                    ErrorToast.fire({ icon: 'error', title: message });
                    break;
                case 'warning':
                    WarningToast.fire({ icon: 'warning', title: message });
                    break;
                case 'info':
                    InfoToast.fire({ icon: 'info', title: message });
                    break;
                default:
                    SuccessToast.fire({ icon: 'success', title: message });
            }
        };

        // Handle session flashes from redirect
        @if(session('status') === 'added-to-cart')
            showToast('success', 'Added to cart');
        @elseif(session('status') === 'wishlist-added')
            showToast('success', 'Added to wishlist');
        @elseif(session('status') === 'wishlist-removed')
            showToast('info', 'Removed from wishlist');
        @endif
        @if(session('error'))
            showToast('error', '{{ session('error') }}');
        @endif
        @if(session('success'))
            showToast('success', '{{ session('success') }}');
        @endif
        @if(session('warning'))
            showToast('warning', '{{ session('warning') }}');
        @endif

        // Listen for Livewire custom toast events
        window.addEventListener('toast', event => {
            const payload = Array.isArray(event.detail) ? event.detail[0] : event.detail;
            showToast(payload.type || 'success', payload.message);
        });
    });
</script>

<style>
    /* Clean slide animations */
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .animate-slideInRight {
        animation: slideInRight 0.3s ease-out;
    }
    
    .animate-slideOutRight {
        animation: slideOutRight 0.3s ease-in;
    }
    
    /* Smaller icon size */
    .swal2-icon {
        margin: 0 !important;
    }
    
    .swal2-icon .swal2-icon-content {
        font-size: 1rem !important;
    }
    
    /* Clean progress bar */
    .swal2-timer-progress-bar {
        border-radius: 0 !important;
    }
    
    /* Remove default icon animations */
    .swal2-icon.swal2-success .swal2-success-ring {
        border: 2px solid #2D9F4E !important;
    }
    
    .swal2-icon.swal2-success [class^='swal2-success-line'] {
        background-color: #2D9F4E !important;
    }
    
    .swal2-icon.swal2-error [class^='swal2-x-mark-line'] {
        background-color: #EF5350 !important;
    }
    
    .swal2-icon.swal2-warning {
        border-color: #FFA726 !important;
        color: #FFA726 !important;
    }
    
    .swal2-icon.swal2-info {
        border-color: #F9C74F !important;
        color: #F9C74F !important;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            background: '#2d6c50',
            color: '#ffffff',
            iconColor: '#ffffff',
            width: 'auto',
            customClass: {
                popup: '!p-3 !py-2 rounded-xl shadow-lg mt-14 mr-4',
                title: '!text-sm !font-semibold !mt-0 !mb-0 text-white',
                timerProgressBar: '!bg-green-300'
            },
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        // Handle session flashes from redirect (like Add to Cart form submissions)
        @if(session('status') === 'added-to-cart')
            Toast.fire({ icon: 'success', title: 'Added to Cart' });
        @elseif(session('status') === 'wishlist-added')
            Toast.fire({ icon: 'success', title: 'Added to Wishlist' });
        @elseif(session('status') === 'wishlist-removed')
            Toast.fire({ icon: 'info', title: 'Removed from Wishlist' });
        @endif
        @if(session('error'))
            Toast.fire({ icon: 'error', title: '{{ session('error') }}' });
        @endif

        // Listen for Livewire custom toast events
        window.addEventListener('toast', event => {
            // Extract payload correctly for Livewire 3
            const payload = Array.isArray(event.detail) ? event.detail[0] : event.detail;
            Toast.fire({
                icon: payload.type || 'success',
                title: payload.message
            });
        });
    });
</script>

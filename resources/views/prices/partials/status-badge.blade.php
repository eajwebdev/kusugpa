{{-- A registry row's status, shown the same way as on the Buy pages --}}
<span class="kp-badge text-xs"
    :class="row.status === 'bought' ? 'kp-badge--forest' : (row.status === 'rejected' ? 'kp-badge--rose' : 'kp-badge--amber')">
    <i class="fas" :class="row.status === 'bought' ? 'fa-check-circle' : (row.status === 'rejected' ? 'fa-times-circle' : 'fa-clock')" aria-hidden="true"></i>
    <span x-text="row.status === 'bought' ? 'Bought' : (row.status === 'rejected' ? 'Rejected' : 'Pending')"></span>
</span>

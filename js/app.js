/* ==========================================================================
   AssetFlow Multi-Page Controller - app.js
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {
    // Elements Cache
    const el = {
        toastContainer: document.getElementById('toast-container'),


        // Notifications
        btnNotifications: document.getElementById('btn-notifications'),
        notifBadgeCount: document.getElementById('notif-badge-count'),
        notificationsDropdown: document.getElementById('notifications-dropdown'),
        notificationsList: document.getElementById('notifications-list'),
        btnMarkAllRead: document.getElementById('btn-mark-all-read'),

        // Quick Action buttons
        btnQuickBooking: document.getElementById('btn-quick-booking'),
        btnQuickMaint: document.getElementById('btn-quick-maint'),
        btnQuickRegister: document.getElementById('btn-quick-register'),

        // Modals
        modalRegisterAsset: document.getElementById('modal-register-asset'),
        modalAllocateAsset: document.getElementById('modal-allocate-asset'),
        modalReturnAsset: document.getElementById('modal-return-asset'),
        modalRequestTransfer: document.getElementById('modal-request-transfer'),
        modalBookResource: document.getElementById('modal-book-resource'),
        modalRaiseMaint: document.getElementById('modal-raise-maint'),
        modalUpdateMaint: document.getElementById('modal-update-maint'),
        modalCreateAudit: document.getElementById('modal-create-audit'),
        modalAssetDetail: document.getElementById('modal-asset-detail'),
        modalCreateDept: document.getElementById('modal-create-dept'),
        modalCreateCategory: document.getElementById('modal-create-category'),
        modalPromoteEmployee: document.getElementById('modal-promote-employee'),

        // Forms
        formRegisterAsset: document.getElementById('form-register-asset'),
        formAllocateAsset: document.getElementById('form-allocate-asset'),
        formReturnAsset: document.getElementById('form-return-asset'),
        formRequestTransfer: document.getElementById('form-request-transfer'),
        formBookResource: document.getElementById('form-book-resource'),
        formRaiseMaint: document.getElementById('form-raise-maint'),
        formUpdateMaint: document.getElementById('form-update-maint'),
        formCreateAudit: document.getElementById('form-create-audit'),
        formCreateDept: document.getElementById('form-create-dept'),
        formCreateCategory: document.getElementById('form-create-category'),
        formPromoteEmployee: document.getElementById('form-promote-employee')
    };

    // ------------------------------------------
    // 1. TOAST SYSTEM
    // ------------------------------------------
    function showToast(message, type = 'success') {
        if (!el.toastContainer) return;
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        let icon = 'ℹ️';
        if (type === 'success') icon = '✓';
        if (type === 'error') icon = '❌';
        if (type === 'warning') icon = '⚠️';

        toast.innerHTML = `<span>${icon}</span> <div>${message}</div>`;
        el.toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideInToast 0.25s reverse ease-in';
            setTimeout(() => toast.remove(), 250);
        }, 3500);
    }

    // Check if session has a toast queued (via localstorage or URL param, but we can do simple ones)
    if (localStorage.getItem('toast_msg')) {
        const msg = localStorage.getItem('toast_msg');
        const type = localStorage.getItem('toast_type') || 'success';
        showToast(msg, type);
        localStorage.removeItem('toast_msg');
        localStorage.removeItem('toast_type');
    }

    // Helper to queue toast across page reload
    function queueToast(message, type = 'success') {
        localStorage.setItem('toast_msg', message);
        localStorage.setItem('toast_type', type);
    }

    // ------------------------------------------
    // 2. AJAX WRAPPER
    // ------------------------------------------
    async function apiRequest(action, method = 'GET', body = null) {
        try {
            const url = `api.php?action=${action}`;
            const options = {
                method
            };

            if (method === 'POST') {
                options.headers = {
                    'Content-Type': 'application/json'
                };
                options.body = JSON.stringify(body);
            }

            const response = await fetch(url, options);
            const result = await response.json();

            if (result.error) {
                if (response.status === 401) {
                    window.location.href = 'login.php';
                }
                throw new Error(result.error);
            }
            return result;
        } catch (error) {
            console.error(`API Error on ${action}:`, error);
            showToast(error.message, 'error');
            throw error;
        }
    }



    // ------------------------------------------
    // 4. NOTIFICATIONS DROPDOWN
    // ------------------------------------------
    if (el.btnNotifications) {
        el.btnNotifications.addEventListener('click', async (e) => {
            e.stopPropagation();
            el.notificationsDropdown.classList.toggle('hidden');

            if (!el.notificationsDropdown.classList.contains('hidden')) {
                // Fetch latest notifications
                try {
                    const data = await apiRequest('get_logs_notifications');
                    renderNotificationsDropdown(data.notifications);
                } catch (err) {}
            }
        });

        // Close on click outside
        document.addEventListener('click', () => {
            el.notificationsDropdown.classList.add('hidden');
        });

        el.notificationsDropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    function renderNotificationsDropdown(list) {
        el.notificationsList.innerHTML = '';

        if (list.length === 0) {
            el.notificationsList.innerHTML = `<div class="empty-state">No new notifications.</div>`;
            return;
        }

        list.forEach(n => {
            const item = document.createElement('div');
            item.className = `notification-item ${!n.is_read ? 'unread' : ''}`;
            item.innerHTML = `
                <span class="notif-title">${n.title}</span>
                <span class="notif-desc">${n.message}</span>
                <span class="notif-time">⌚ ${n.created_at}</span>
            `;

            item.addEventListener('click', async () => {
                if (!n.is_read) {
                    try {
                        await apiRequest('mark_notification_read', 'POST', {
                            notification_id: n.id
                        });
                        // Update UI locally
                        item.classList.remove('unread');
                        const count = parseInt(el.notifBadgeCount.textContent) - 1;
                        if (count > 0) {
                            el.notifBadgeCount.textContent = count;
                        } else {
                            el.notifBadgeCount.classList.add('hidden');
                        }
                    } catch (e) {}
                }
            });

            el.notificationsList.appendChild(item);
        });
    }

    if (el.btnMarkAllRead) {
        el.btnMarkAllRead.addEventListener('click', async () => {
            try {
                await apiRequest('mark_notification_read', 'POST', {
                    notification_id: 'all'
                });
                el.notifBadgeCount.classList.add('hidden');
                el.notificationsList.innerHTML = `<div class="empty-state">No new notifications.</div>`;
                showToast('All notifications dismissed.', 'info');
            } catch (e) {}
        });
    }

    // ------------------------------------------
    // 5. ASSET DETAILS timeline drawer
    // ------------------------------------------
    async function openAssetDetailDrawer(assetId) {
        try {
            const data = await apiRequest(`get_asset_details&id=${assetId}`);
            const asset = data.asset;
            const active = data.active_allocation;

            document.getElementById('detail-title').textContent = `${asset.name}`;
            document.getElementById('detail-tag-label').textContent = asset.tag;

            // Visual QR code using QR Server API
            const qrBlock = document.getElementById('detail-qr-code');
            qrBlock.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=${encodeURIComponent(asset.tag)}" alt="QR Code" style="display:block; margin:0 auto; border:4px solid #fff; border-radius:4px; box-shadow:0 0 10px rgba(0,0,0,0.25); width:130px; height:130px;" />`;

            // Specs metadata list
            const specsBody = document.getElementById('detail-specs-list');
            specsBody.innerHTML = `
                <div class="spec-item"><span class="spec-name">Asset ID Code</span><span class="spec-val text-success">${asset.tag}</span></div>
                <div class="spec-item"><span class="spec-name">Serial Number</span><span class="spec-val">${asset.serial_number}</span></div>
                <div class="spec-item"><span class="spec-name">Purchase Date</span><span class="spec-val">${asset.acquisition_date}</span></div>
                <div class="spec-item"><span class="spec-name">Cost</span><span class="spec-val">$${parseFloat(asset.acquisition_cost).toLocaleString()}</span></div>
                <div class="spec-item"><span class="spec-name">Location</span><span class="spec-val">${asset.location}</span></div>
                <div class="spec-item"><span class="spec-name">Status</span><span class="status-pill status-${getAssetStatusClass(asset.status)}">${asset.status}</span></div>
                <div class="spec-item"><span class="spec-name">Type</span><span class="spec-val">${asset.is_shared ? 'Shared Resource' : 'Individual Allocation'}</span></div>
            `;

            try {
                const customFields = JSON.parse(asset.category_fields || '{}');
                for (const [key, val] of Object.entries(customFields)) {
                    specsBody.innerHTML += `<div class="spec-item"><span class="spec-name">${key.replace('_', ' ').toUpperCase()}</span><span class="spec-val">${val}</span></div>`;
                }
            } catch (e) {}

            // Allocation Timeline
            const allocTimeline = document.getElementById('detail-allocations-timeline');
            allocTimeline.innerHTML = '';
            if (data.allocation_history.length === 0) {
                allocTimeline.innerHTML = '<li class="timeline-item"><p class="text-muted">No allocations recorded.</p></li>';
            } else {
                data.allocation_history.forEach(hist => {
                    const isActive = hist.status === 'Active' || hist.status === 'Overdue';
                    const li = document.createElement('li');
                    li.className = `timeline-item ${isActive ? (hist.status === 'Overdue' ? 'warning-event' : 'active-event') : ''}`;
                    li.innerHTML = `
                        <span class="timeline-date">${hist.allocation_date}</span>
                        <div class="timeline-content">
                            <h5>Allocated to ${hist.employee_name || hist.department_name || 'Department'}</h5>
                            <p>Allocated by ${hist.allocator_name || 'Admin'}</p>
                            ${hist.expected_return_date ? `<p>Expected Due: <strong>${hist.expected_return_date}</strong></p>` : ''}
                            ${hist.actual_return_date ? `<p class="text-success">Returned: ${hist.actual_return_date} (Condition: ${hist.condition_on_return})</p>` : ''}
                            ${hist.status === 'Overdue' ? '<strong class="text-danger">⚠️ Return Overdue!</strong>' : ''}
                        </div>
                    `;
                    allocTimeline.appendChild(li);
                });
            }

            // Maintenance Timeline
            const maintTimeline = document.getElementById('detail-maintenance-timeline');
            maintTimeline.innerHTML = '';
            if (data.maintenance_history.length === 0) {
                maintTimeline.innerHTML = '<li class="timeline-item"><p class="text-muted">No repairs logged.</p></li>';
            } else {
                data.maintenance_history.forEach(m => {
                    const li = document.createElement('li');
                    li.className = `timeline-item ${m.status !== 'Resolved' ? 'warning-event' : ''}`;
                    li.innerHTML = `
                        <span class="timeline-date">${m.created_at}</span>
                        <div class="timeline-content">
                            <h5>🔧 ${m.status} | Priority: ${m.priority}</h5>
                            <p>${m.description}</p>
                            ${m.assigned_technician ? `<p>Technician: <strong>${m.assigned_technician}</strong></p>` : ''}
                            ${m.notes ? `<p>Notes: <em>${m.notes}</em></p>` : ''}
                        </div>
                    `;
                    maintTimeline.appendChild(li);
                });
            }

            // Footer actions
            const footerActions = document.getElementById('detail-footer-actions');
            footerActions.innerHTML = '';

            // Read role simulated
            const userRole = document.getElementById('user-role-badge').textContent.toLowerCase().replace(' ', '_');
            const isManager = (userRole === 'admin' || userRole === 'asset_manager');

            if (asset.status === 'Available') {
                if (isManager) {
                    footerActions.innerHTML += `<button class="btn btn-primary" id="btn-detail-allocate">Allocate Asset</button>`;
                }
                if (asset.is_shared) {
                    footerActions.innerHTML += `<button class="btn btn-secondary" id="btn-detail-book">Book Slot</button>`;
                }
            } else if (asset.status === 'Allocated') {
                if (isManager && active) {
                    footerActions.innerHTML += `<button class="btn btn-danger" id="btn-detail-return">Check-In Return</button>`;
                }
                footerActions.innerHTML += `<button class="btn btn-secondary" id="btn-detail-transfer">Request Transfer</button>`;
            } else if (asset.status === 'Under Maintenance') {
                if (isManager) {
                    const activeTicket = data.maintenance_history.find(h => h.status !== 'Resolved' && h.status !== 'Rejected');
                    if (activeTicket) {
                        footerActions.innerHTML += `<button class="btn btn-primary" id="btn-detail-update-maint">Resolve / Update Repair</button>`;
                    }
                }
            }

            // Bind triggers
            const allocBtn = document.getElementById('btn-detail-allocate');
            if (allocBtn) {
                allocBtn.onclick = () => {
                    closeModal(el.modalAssetDetail);
                    openAllocateModal(asset.id);
                };
            }

            const bookBtn = document.getElementById('btn-detail-book');
            if (bookBtn) {
                bookBtn.onclick = () => {
                    closeModal(el.modalAssetDetail);
                    openBookModal(asset.id);
                };
            }

            const returnBtn = document.getElementById('btn-detail-return');
            if (returnBtn) {
                returnBtn.onclick = () => {
                    closeModal(el.modalAssetDetail);
                    openReturnModal(active.id);
                };
            }

            const transferBtn = document.getElementById('btn-detail-transfer');
            if (transferBtn) {
                transferBtn.onclick = () => {
                    closeModal(el.modalAssetDetail);
                    openTransferModal(asset.tag);
                };
            }

            const updateMaintBtn = document.getElementById('btn-detail-update-maint');
            if (updateMaintBtn) {
                updateMaintBtn.onclick = () => {
                    const activeTicket = data.maintenance_history.find(h => h.status !== 'Resolved' && h.status !== 'Rejected');
                    closeModal(el.modalAssetDetail);
                    document.getElementById('update-maint-id').value = activeTicket.id;
                    document.getElementById('maint-state-select').value = activeTicket.status === 'Approved' ? 'Technician Assigned' : activeTicket.status;
                    document.getElementById('maint-tech-name').value = activeTicket.assigned_technician || '';
                    document.getElementById('maint-notes-area').value = activeTicket.notes || '';
                    toggleTechInputVisibility();
                    openModal(el.modalUpdateMaint);
                };
            }

            openModal(el.modalAssetDetail);

        } catch (e) {}
    }

    function getAssetStatusClass(status) {
        const map = {
            'Available': 'available',
            'Allocated': 'allocated',
            'Reserved': 'reserved',
            'Under Maintenance': 'maint',
            'Lost': 'lost',
            'Retired': 'retired',
            'Disposed': 'disposed'
        };
        return map[status] || 'retired';
    }

    // Bind triggers in grids and lists
    document.querySelectorAll('.btn-detail-trigger').forEach(item => {
        item.addEventListener('click', (e) => {
            if (e.target.tagName === 'BUTTON' || e.target.closest('button')) return;
            const assetId = item.getAttribute('data-id');
            openAssetDetailDrawer(assetId);
        });
    });

    document.querySelectorAll('.btn-open-detail').forEach(b => {
        b.addEventListener('click', () => openAssetDetailDrawer(b.getAttribute('data-id')));
    });

    // ------------------------------------------
    // 6. MODALS UTILITIES
    // ------------------------------------------
    function openModal(modal) {
        if (modal) modal.classList.remove('hidden');
    }

    function closeModal(modal) {
        if (modal) modal.classList.add('hidden');
    }

    document.querySelectorAll('.modal-close, .modal-cancel, .modal-close-btn').forEach(closeBtn => {
        closeBtn.addEventListener('click', () => {
            const modal = closeBtn.closest('.modal-backdrop');
            closeModal(modal);
        });
    });

    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) {
                closeModal(backdrop);
            }
        });
    });

    // ------------------------------------------
    // 7. MODALS DATA POPULATORS
    // ------------------------------------------
    async function openAllocateModal(preSelectedAssetId = null) {
        try {
            const assets = await apiRequest('get_assets&status=Available');
            const assetSelect = document.getElementById('alloc-asset-id');
            assetSelect.innerHTML = '<option value="">Select Available Asset</option>';
            assets.forEach(a => {
                assetSelect.innerHTML += `<option value="${a.id}">${a.tag} - ${a.name}</option>`;
            });
            if (preSelectedAssetId) assetSelect.value = preSelectedAssetId;

            const org = await apiRequest('get_org_setup');
            const empSelect = document.getElementById('alloc-employee-id');
            empSelect.innerHTML = '<option value="">Select Employee</option>';
            org.employees.forEach(emp => {
                if (emp.status === 'Active') {
                    empSelect.innerHTML += `<option value="${emp.id}">${emp.name} (${emp.email})</option>`;
                }
            });

            const deptSelect = document.getElementById('alloc-department-id');
            deptSelect.innerHTML = '<option value="">Select Department</option>';
            org.departments.forEach(dept => {
                if (dept.status === 'Active') {
                    deptSelect.innerHTML += `<option value="${dept.id}">${dept.name}</option>`;
                }
            });

            openModal(el.modalAllocateAsset);
        } catch (e) {}
    }

    document.querySelectorAll('input[name="alloc-target"]').forEach(radio => {
        radio.addEventListener('change', () => {
            if (radio.value === 'employee') {
                document.getElementById('alloc-employee-group').classList.remove('hidden');
                document.getElementById('alloc-department-group').classList.add('hidden');
            } else {
                document.getElementById('alloc-employee-group').classList.add('hidden');
                document.getElementById('alloc-department-group').classList.remove('hidden');
            }
        });
    });

    function openReturnModal(allocationId) {
        document.getElementById('return-alloc-id').value = allocationId;
        document.getElementById('return-condition').value = 'Good';
        document.getElementById('return-notes').value = '';
        openModal(el.modalReturnAsset);
    }

    async function openTransferModal(preSelectedTag = '') {
        document.getElementById('trans-asset-tag').value = preSelectedTag;
        try {
            const org = await apiRequest('get_org_setup');
            const empSelect = document.getElementById('trans-employee-id');
            empSelect.innerHTML = '<option value="">Select Employee</option>';
            org.employees.forEach(emp => {
                if (emp.status === 'Active') {
                    empSelect.innerHTML += `<option value="${emp.id}">${emp.name} (${emp.email})</option>`;
                }
            });

            const deptSelect = document.getElementById('trans-department-id');
            deptSelect.innerHTML = '<option value="">Select Department</option>';
            org.departments.forEach(dept => {
                if (dept.status === 'Active') {
                    deptSelect.innerHTML += `<option value="${dept.id}">${dept.name}</option>`;
                }
            });

            openModal(el.modalRequestTransfer);
        } catch (e) {}
    }

    document.querySelectorAll('input[name="trans-target"]').forEach(radio => {
        radio.addEventListener('change', () => {
            if (radio.value === 'employee') {
                document.getElementById('trans-employee-group').classList.remove('hidden');
                document.getElementById('trans-department-group').classList.add('hidden');
            } else {
                document.getElementById('trans-employee-group').classList.add('hidden');
                document.getElementById('trans-department-group').classList.remove('hidden');
            }
        });
    });

    async function openBookModal(preSelectedAssetId = null) {
        try {
            const assets = await apiRequest('get_assets&bookable=1');
            const bookSelect = document.getElementById('book-asset-id');
            bookSelect.innerHTML = '<option value="">Select Room / Vehicle / Table</option>';
            assets.forEach(a => {
                bookSelect.innerHTML += `<option value="${a.id}">${a.tag} - ${a.name} (${a.location})</option>`;
            });
            if (preSelectedAssetId) bookSelect.value = preSelectedAssetId;

            document.getElementById('book-date').value = new Date().toISOString().substring(0, 10);
            document.getElementById('book-start').value = '09:00';
            document.getElementById('book-end').value = '10:00';

            openModal(el.modalBookResource);
        } catch (e) {}
    }

    async function openRaiseMaintModal(preSelectedAssetId = null) {
        try {
            const assets = await apiRequest('get_assets');
            const maintSelect = document.getElementById('maint-asset-id');
            maintSelect.innerHTML = '<option value="">Select Asset</option>';
            assets.forEach(a => {
                maintSelect.innerHTML += `<option value="${a.id}">${a.tag} - ${a.name}</option>`;
            });
            if (preSelectedAssetId) maintSelect.value = preSelectedAssetId;

            document.getElementById('maint-priority').value = 'Medium';
            document.getElementById('maint-desc').value = '';

            openModal(el.modalRaiseMaint);
        } catch (e) {}
    }

    // Attach returns on clicks (dashboard list, allocations tab)
    document.querySelectorAll('.btn-return-action').forEach(b => {
        b.addEventListener('click', () => {
            openReturnModal(b.getAttribute('data-alloc-id'));
        });
    });

    // ------------------------------------------
    // 8. QUICK ACTIONS BINDINGS
    // ------------------------------------------
    if (el.btnQuickBooking) el.btnQuickBooking.addEventListener('click', () => openBookModal());
    if (el.btnQuickMaint) el.btnQuickMaint.addEventListener('click', () => openRaiseMaintModal());
    if (el.btnQuickRegister) {
        el.btnQuickRegister.addEventListener('click', async () => {
            try {
                const org = await apiRequest('get_org_setup');
                const catSelect = document.getElementById('reg-category');
                catSelect.innerHTML = '<option value="">Select Category</option>';
                org.categories.forEach(c => {
                    catSelect.innerHTML += `<option value="${c.id}">${c.name}</option>`;
                });

                document.getElementById('reg-name').value = '';
                document.getElementById('reg-serial').value = '';
                document.getElementById('reg-location').value = '';
                document.getElementById('reg-cost').value = '';
                document.getElementById('reg-shared').checked = false;

                openModal(el.modalRegisterAsset);
            } catch (e) {}
        });
    }

    // ------------------------------------------
    // 9. FORM SUBMIT HANDLERS
    // ------------------------------------------

    // Register Asset
    if (el.formRegisterAsset) {
        el.formRegisterAsset.addEventListener('submit', async (e) => {
            e.preventDefault();
            const body = {
                name: document.getElementById('reg-name').value,
                category_id: document.getElementById('reg-category').value,
                serial_number: document.getElementById('reg-serial').value,
                acquisition_date: document.getElementById('reg-date').value,
                acquisition_cost: document.getElementById('reg-cost').value,
                condition_state: document.getElementById('reg-condition').value,
                location: document.getElementById('reg-location').value,
                is_shared: document.getElementById('reg-shared').checked ? 1 : 0
            };
            try {
                const res = await apiRequest('register_asset', 'POST', body);
                if (res.success) {
                    queueToast(res.success, 'success');
                    window.location.reload();
                }
            } catch (err) {}
        });
    }

    // Allocate Asset
    if (el.formAllocateAsset) {
        el.formAllocateAsset.addEventListener('submit', async (e) => {
            e.preventDefault();
            const targetType = document.querySelector('input[name="alloc-target"]:checked').value;
            const body = {
                asset_id: document.getElementById('alloc-asset-id').value,
                employee_id: targetType === 'employee' ? document.getElementById('alloc-employee-id').value : null,
                department_id: targetType === 'department' ? document.getElementById('alloc-department-id').value : null,
                expected_return_date: document.getElementById('alloc-return-date').value
            };
            try {
                const res = await apiRequest('allocate_asset', 'POST', body);
                if (res.success) {
                    queueToast(res.success, 'success');
                    window.location.reload();
                }
            } catch (err) {}
        });
    }

    // Return Asset
    if (el.formReturnAsset) {
        el.formReturnAsset.addEventListener('submit', async (e) => {
            e.preventDefault();
            const body = {
                allocation_id: document.getElementById('return-alloc-id').value,
                condition_on_return: document.getElementById('return-condition').value,
                notes: document.getElementById('return-notes').value
            };
            try {
                const res = await apiRequest('return_asset', 'POST', body);
                if (res.success) {
                    queueToast(res.success, 'success');
                    window.location.reload();
                }
            } catch (err) {}
        });
    }

    // Request Transfer
    if (el.formRequestTransfer) {
        el.formRequestTransfer.addEventListener('submit', async (e) => {
            e.preventDefault();
            const targetType = document.querySelector('input[name="trans-target"]:checked').value;
            const body = {
                asset_tag: document.getElementById('trans-asset-tag').value,
                to_employee_id: targetType === 'employee' ? document.getElementById('trans-employee-id').value : null,
                to_department_id: targetType === 'department' ? document.getElementById('trans-department-id').value : null
            };
            try {
                const res = await apiRequest('request_transfer', 'POST', body);
                if (res.success) {
                    queueToast(res.success, 'success');
                    window.location.reload();
                }
            } catch (err) {}
        });
    }

    // Book Resource
    if (el.formBookResource) {
        el.formBookResource.addEventListener('submit', async (e) => {
            e.preventDefault();
            const body = {
                asset_id: document.getElementById('book-asset-id').value,
                booking_date: document.getElementById('book-date').value,
                start_time: document.getElementById('book-start').value,
                end_time: document.getElementById('book-end').value
            };
            try {
                const res = await apiRequest('book_resource', 'POST', body);
                if (res.success) {
                    queueToast(res.success, 'success');
                    window.location.reload();
                }
            } catch (err) {}
        });
    }

    // Raise Maintenance request
    if (el.formRaiseMaint) {
        el.formRaiseMaint.addEventListener('submit', async (e) => {
            e.preventDefault();
            const body = {
                asset_id: document.getElementById('maint-asset-id').value,
                priority: document.getElementById('maint-priority').value,
                description: document.getElementById('maint-desc').value
            };
            try {
                const res = await apiRequest('raise_maintenance', 'POST', body);
                if (res.success) {
                    queueToast(res.success, 'success');
                    window.location.reload();
                }
            } catch (err) {}
        });
    }

    // Update Maintenance Task Status
    if (el.formUpdateMaint) {
        el.formUpdateMaint.addEventListener('submit', async (e) => {
            e.preventDefault();
            const body = {
                request_id: document.getElementById('update-maint-id').value,
                status: document.getElementById('maint-state-select').value,
                assigned_technician: document.getElementById('maint-tech-name').value,
                notes: document.getElementById('maint-notes-area').value
            };
            try {
                const res = await apiRequest('update_maintenance_status', 'POST', body);
                if (res.success) {
                    queueToast(res.success, 'success');
                    window.location.reload();
                }
            } catch (err) {}
        });
    }

    // Create Audit Cycle
    if (el.formCreateAudit) {
        el.formCreateAudit.addEventListener('submit', async (e) => {
            e.preventDefault();
            const select = document.getElementById('audit-auditors-multiselect');
            const auditorIds = Array.from(select.selectedOptions).map(opt => opt.value);
            const body = {
                name: document.getElementById('audit-name').value,
                department_id: document.getElementById('audit-department-id').value,
                location: document.getElementById('audit-location').value,
                start_date: document.getElementById('audit-start').value,
                end_date: document.getElementById('audit-end').value,
                auditor_ids: auditorIds
            };
            try {
                const res = await apiRequest('create_audit_cycle', 'POST', body);
                if (res.success) {
                    queueToast(res.success, 'success');
                    window.location.reload();
                }
            } catch (err) {}
        });
    }

    // ------------------------------------------
    // 10. MULTI-PAGE VIEW SPECIFIC TRIGGERS
    // ------------------------------------------

    // --- VIEW: DASHBOARD ---
    document.querySelectorAll('.btn-approve-transfer').forEach(b => {
        b.addEventListener('click', () => handleTransferApproval(b.getAttribute('data-id'), 'Approved'));
    });
    document.querySelectorAll('.btn-reject-transfer').forEach(b => {
        b.addEventListener('click', () => handleTransferApproval(b.getAttribute('data-id'), 'Rejected'));
    });
    document.querySelectorAll('.btn-approve-maint').forEach(b => {
        b.addEventListener('click', () => handleMaintenanceApproval(b.getAttribute('data-id'), 'Approved'));
    });
    document.querySelectorAll('.btn-reject-maint').forEach(b => {
        b.addEventListener('click', () => handleMaintenanceApproval(b.getAttribute('data-id'), 'Rejected'));
    });

    async function handleTransferApproval(id, decision) {
        try {
            const res = await apiRequest('approve_transfer', 'POST', {
                transfer_id: id,
                decision
            });
            if (res.success) {
                queueToast(res.success, 'success');
                window.location.reload();
            }
        } catch (e) {}
    }

    async function handleMaintenanceApproval(id, decision) {
        try {
            const res = await apiRequest('approve_maintenance', 'POST', {
                request_id: id,
                decision
            });
            if (res.success) {
                queueToast(res.success, 'success');
                window.location.reload();
            }
        } catch (e) {}
    }

    // --- VIEW: ORGANIZATIONAL MASTER DATA ---
    const btnAddDept = document.getElementById('btn-add-dept');
    if (btnAddDept) {
        btnAddDept.addEventListener('click', async () => {
            try {
                const org = await apiRequest('get_org_setup');
                const parentSelect = document.getElementById('dept-parent');
                parentSelect.innerHTML = '<option value="">None (Top Level)</option>';
                org.departments.forEach(d => {
                    parentSelect.innerHTML += `<option value="${d.id}">${d.name}</option>`;
                });

                document.getElementById('modal-dept-title').textContent = 'Create Department';
                document.getElementById('dept-id-edit').value = '';
                document.getElementById('dept-name').value = '';
                document.getElementById('dept-status').value = 'Active';
                document.getElementById('dept-head-group').style.display = 'none';

                openModal(el.modalCreateDept);
            } catch (e) {}
        });
    }

    document.querySelectorAll('.btn-edit-dept').forEach(b => {
        b.addEventListener('click', async () => {
            try {
                const org = await apiRequest('get_org_setup');

                const parentSelect = document.getElementById('dept-parent');
                parentSelect.innerHTML = '<option value="">None (Top Level)</option>';
                org.departments.forEach(d => {
                    if (d.id != b.getAttribute('data-id')) { // Prevent parenting to self
                        parentSelect.innerHTML += `<option value="${d.id}">${d.name}</option>`;
                    }
                });

                const headSelect = document.getElementById('dept-head');
                headSelect.innerHTML = '<option value="">Select Employee</option>';
                org.employees.forEach(emp => {
                    headSelect.innerHTML += `<option value="${emp.id}">${emp.name} (${emp.email})</option>`;
                });

                document.getElementById('modal-dept-title').textContent = 'Edit Department';
                document.getElementById('dept-id-edit').value = b.getAttribute('data-id');
                document.getElementById('dept-name').value = b.getAttribute('data-name');
                document.getElementById('dept-parent').value = b.getAttribute('data-parent');
                document.getElementById('dept-head').value = b.getAttribute('data-head');
                document.getElementById('dept-status').value = b.getAttribute('data-status');
                document.getElementById('dept-head-group').style.display = 'block';

                openModal(el.modalCreateDept);
            } catch (e) {}
        });
    });

    if (el.formCreateDept) {
        el.formCreateDept.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('dept-id-edit').value;
            const body = {
                id,
                name: document.getElementById('dept-name').value,
                parent_id: document.getElementById('dept-parent').value,
                status: document.getElementById('dept-status').value,
                head_id: id ? document.getElementById('dept-head').value : null
            };
            const action = id ? 'edit_department' : 'add_department';
            try {
                const res = await apiRequest(action, 'POST', body);
                if (res.success) {
                    queueToast(res.success, 'success');
                    window.location.reload();
                }
            } catch (err) {}
        });
    }

    const btnAddCategory = document.getElementById('btn-add-category');
    if (btnAddCategory) {
        btnAddCategory.addEventListener('click', () => {
            document.getElementById('modal-cat-title').textContent = 'Create Category';
            document.getElementById('cat-id-edit').value = '';
            document.getElementById('cat-name').value = '';
            document.getElementById('category-fields-list').innerHTML = '';
            openModal(el.modalCreateCategory);
        });
    }

    document.querySelectorAll('.btn-edit-cat').forEach(b => {
        b.addEventListener('click', () => {
            document.getElementById('modal-cat-title').textContent = 'Edit Category';
            document.getElementById('cat-id-edit').value = b.getAttribute('data-id');
            document.getElementById('cat-name').value = b.getAttribute('data-name');

            const fieldsList = document.getElementById('category-fields-list');
            fieldsList.innerHTML = '';
            const fields = JSON.parse(b.getAttribute('data-fields') || '[]');
            fields.forEach(f => addCategoryAttributeRow(f.name, f.type));

            openModal(el.modalCreateCategory);
        });
    });

    const btnAddCatField = document.getElementById('btn-add-cat-field');
    if (btnAddCatField) {
        btnAddCatField.addEventListener('click', () => addCategoryAttributeRow());
    }

    function addCategoryAttributeRow(name = '', type = 'text') {
        const list = document.getElementById('category-fields-list');
        const row = document.createElement('div');
        row.className = 'cat-attribute-row';
        row.innerHTML = `
            <input type="text" class="form-control field-name" placeholder="Attribute Name" value="${name}" required style="flex-grow:1;">
            <select class="form-control field-type" style="width:120px;">
                <option value="text" ${type === 'text' ? 'selected' : ''}>Text</option>
                <option value="number" ${type === 'number' ? 'selected' : ''}>Number</option>
                <option value="date" ${type === 'date' ? 'selected' : ''}>Date</option>
            </select>
            <button type="button" class="btn btn-danger btn-sm btn-remove-field" style="height:38px;">Remove</button>
        `;
        row.querySelector('.btn-remove-field').onclick = () => row.remove();
        list.appendChild(row);
    }

    if (el.formCreateCategory) {
        el.formCreateCategory.addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('cat-id-edit').value;
            const customFields = [];
            document.querySelectorAll('.cat-attribute-row').forEach(row => {
                const name = row.querySelector('.field-name').value;
                const type = row.querySelector('.field-type').value;
                if (name) customFields.push({
                    name,
                    type
                });
            });
            const body = {
                id,
                name: document.getElementById('cat-name').value,
                custom_fields: customFields
            };
            const action = id ? 'edit_category' : 'add_category';
            try {
                const res = await apiRequest(action, 'POST', body);
                if (res.success) {
                    queueToast(res.success, 'success');
                    window.location.reload();
                }
            } catch (err) {}
        });
    }

    document.querySelectorAll('.btn-promote-emp').forEach(b => {
        b.addEventListener('click', async () => {
            try {
                const org = await apiRequest('get_org_setup');
                const promoteDeptSelect = document.getElementById('promote-department-id');
                promoteDeptSelect.innerHTML = '<option value="">Select Department</option>';
                org.departments.forEach(d => {
                    promoteDeptSelect.innerHTML += `<option value="${d.id}">${d.name}</option>`;
                });

                document.getElementById('promote-emp-id').value = b.getAttribute('data-id');
                document.getElementById('promote-name-readonly').value = b.getAttribute('data-name');
                document.getElementById('promote-role').value = b.getAttribute('data-role');
                document.getElementById('promote-status').value = b.getAttribute('data-status');
                promoteDeptSelect.value = b.getAttribute('data-dept');

                openModal(el.modalPromoteEmployee);
            } catch (e) {}
        });
    });

    if (el.formPromoteEmployee) {
        el.formPromoteEmployee.addEventListener('submit', async (e) => {
            e.preventDefault();
            const body = {
                employee_id: document.getElementById('promote-emp-id').value,
                role: document.getElementById('promote-role').value,
                department_id: document.getElementById('promote-department-id').value,
                status: document.getElementById('promote-status').value
            };
            try {
                const res = await apiRequest('promote_employee', 'POST', body);
                if (res.success) {
                    queueToast(res.success, 'success');
                    window.location.reload();
                }
            } catch (err) {}
        });
    }

    // Tab buttons swapper
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const container = btn.closest('.tab-container');
            container.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            container.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            const targetId = btn.getAttribute('data-tab');
            document.getElementById(targetId).classList.add('active');
        });
    });

    // Sub tabs inside allocations
    document.querySelectorAll('.sub-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const container = btn.closest('.allocations-container');
            container.querySelectorAll('.sub-tab-btn').forEach(b => b.classList.remove('active'));
            container.querySelectorAll('.sub-tab-pane').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            const targetId = btn.getAttribute('data-subtab');
            document.getElementById(targetId).classList.add('active');
        });
    });

    // Check url hash on allocations to swap tab automatically
    if (window.location.hash === '#subtab-transfers') {
        const transBtn = document.querySelector('[data-subtab="subtab-transfers"]');
        if (transBtn) transBtn.click();
    }

    // --- VIEW: BOOKINGS ---
    document.querySelectorAll('.btn-cancel-booking').forEach(b => {
        b.addEventListener('click', async () => {
            if (!confirm('Are you sure you want to cancel this booking?')) return;
            const bookingId = b.getAttribute('data-id');
            try {
                const res = await apiRequest('cancel_booking', 'POST', {
                    booking_id: bookingId
                });
                if (res.success) {
                    queueToast(res.success, 'success');
                    window.location.reload();
                }
            } catch (e) {}
        });
    });

    // --- VIEW: MAINTENANCE ---
    document.querySelectorAll('.btn-update-maint').forEach(b => {
        b.addEventListener('click', () => {
            document.getElementById('update-maint-id').value = b.getAttribute('data-id');
            document.getElementById('maint-state-select').value = b.getAttribute('data-status') === 'Approved' ? 'Technician Assigned' : b.getAttribute('data-status');
            document.getElementById('maint-tech-name').value = b.getAttribute('data-tech');
            document.getElementById('maint-notes-area').value = b.getAttribute('data-notes');
            toggleTechInputVisibility();
            openModal(el.modalUpdateMaint);
        });
    });

    const btnRaiseMaintModal = document.getElementById('btn-raise-maintenance-modal');
    if (btnRaiseMaintModal) {
        btnRaiseMaintModal.onclick = () => openRaiseMaintModal();
    }

    const maintStateSelect = document.getElementById('maint-state-select');
    if (maintStateSelect) {
        maintStateSelect.addEventListener('change', toggleTechInputVisibility);
    }

    function toggleTechInputVisibility() {
        if (!maintStateSelect) return;
        const value = maintStateSelect.value;
        if (value === 'Technician Assigned' || value === 'In Progress') {
            document.getElementById('maint-tech-input-group').classList.remove('hidden');
        } else {
            document.getElementById('maint-tech-input-group').classList.add('hidden');
        }
    }

    // --- VIEW: AUDITS ---
    const btnCreateAuditModal = document.getElementById('btn-create-audit-modal');
    if (btnCreateAuditModal) {
        btnCreateAuditModal.addEventListener('click', async () => {
            try {
                const org = await apiRequest('get_org_setup');
                const deptSelect = document.getElementById('audit-department-id');
                deptSelect.innerHTML = '<option value="">All Departments</option>';
                org.departments.forEach(d => {
                    if (d.status === 'Active') deptSelect.innerHTML += `<option value="${d.id}">${d.name}</option>`;
                });

                const auditorsSelect = document.getElementById('audit-auditors-multiselect');
                auditorsSelect.innerHTML = '';
                org.employees.forEach(emp => {
                    if (emp.status === 'Active' && (emp.role === 'admin' || emp.role === 'asset_manager')) {
                        auditorsSelect.innerHTML += `<option value="${emp.id}">${emp.name} (${emp.role.replace('_', ' ')})</option>`;
                    }
                });

                document.getElementById('audit-name').value = '';
                document.getElementById('audit-location').value = '';
                document.getElementById('audit-start').value = new Date().toISOString().substring(0, 10);
                const endDate = new Date();
                endDate.setDate(endDate.getDate() + 30);
                document.getElementById('audit-end').value = endDate.toISOString().substring(0, 10);

                openModal(el.modalCreateAudit);
            } catch (e) {}
        });
    }

    document.querySelectorAll('.btn-audit-state').forEach(b => {
        b.addEventListener('click', async () => {
            const itemId = b.getAttribute('data-id');
            const status = b.getAttribute('data-status');
            const row = b.closest('tr');
            const notes = row.querySelector('.audit-notes-input').value;

            try {
                await apiRequest('update_audit_item', 'POST', {
                    item_id: itemId,
                    status,
                    notes
                });
                queueToast('Audit item status logged.', 'success');
                window.location.reload();
            } catch (e) {}
        });
    });

    document.querySelectorAll('.audit-notes-input').forEach(input => {
        input.addEventListener('change', async () => {
            const itemId = input.getAttribute('data-id');
            const row = input.closest('tr');
            const statusPill = row.querySelector('.status-pill').textContent.trim();
            if (statusPill !== 'Pending') {
                try {
                    await apiRequest('update_audit_item', 'POST', {
                        item_id: itemId,
                        status: statusPill,
                        notes: input.value
                    });
                } catch (e) {}
            }
        });
    });

    window.closeAuditCycle = async function(cycleId) {
        if (!confirm('Are you sure you want to close this audit cycle? Missing assets will automatically be flagged as Lost.')) return;
        try {
            const res = await apiRequest('close_audit_cycle', 'POST', {
                cycle_id: cycleId
            });
            if (res.success) {
                queueToast(res.success, 'success');
                window.location.reload();
            }
        } catch (e) {}
    };

    window.openBookModal = function(resourceId) {
        openBookModal(resourceId);
    };

    // ------------------------------------------
    // 11. QR CODE SCANNER CONTROLLER
    // ------------------------------------------
    const btnScanQr = document.getElementById('btn-scan-qr');
    const modalQrScanner = document.getElementById('modal-qr-scanner');
    const btnStopQr = document.getElementById('btn-stop-qr');
    const btnCloseQrReader = document.getElementById('btn-close-qr-reader');
    let html5QrCodeScanner = null;

    if (btnScanQr) {
        btnScanQr.addEventListener('click', () => {
            openModal(modalQrScanner);
            document.getElementById('qr-reader-results').textContent = '';

            // Start scanning
            try {
                html5QrCodeScanner = new Html5Qrcode("qr-reader");
                const qrCodeSuccessCallback = (decodedText, decodedResult) => {
                    document.getElementById('qr-reader-results').textContent = `Scanned Tag: ${decodedText}`;

                    stopScanner();
                    closeModal(modalQrScanner);
                    showToast(`Scanned Asset: ${decodedText}`, 'success');

                    const searchInput = document.getElementById('asset-search');
                    if (searchInput) {
                        searchInput.value = decodedText;
                        searchInput.closest('form').submit();
                    } else {
                        window.location.href = `assets.php?search=${encodeURIComponent(decodedText)}`;
                    }
                };
                const config = {
                    fps: 10,
                    qrbox: {
                        width: 250,
                        height: 250
                    }
                };

                html5QrCodeScanner.start({
                        facingMode: "environment"
                    }, config, qrCodeSuccessCallback)
                    .catch(err => {
                        console.warn("Camera start failed, testing alternative camera device", err);
                        html5QrCodeScanner.start(0, config, qrCodeSuccessCallback)
                            .catch(e => {
                                document.getElementById('qr-reader-results').innerHTML = `<span style="color:var(--color-danger)">Camera unavailable or blocked.</span>`;
                            });
                    });
            } catch (e) {
                document.getElementById('qr-reader-results').textContent = "Scanner load error.";
            }
        });
    }

    function stopScanner() {
        if (html5QrCodeScanner) {
            try {
                html5QrCodeScanner.stop().then(() => {
                    html5QrCodeScanner = null;
                }).catch(err => {
                    console.error("Error stopping scanner", err);
                    html5QrCodeScanner = null;
                });
            } catch (e) {
                html5QrCodeScanner = null;
            }
        }
    }

    if (btnStopQr) {
        btnStopQr.addEventListener('click', () => {
            stopScanner();
            closeModal(modalQrScanner);
        });
    }

    if (btnCloseQrReader) {
        btnCloseQrReader.addEventListener('click', () => {
            stopScanner();
            closeModal(modalQrScanner);
        });
    }
});
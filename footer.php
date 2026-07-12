            </main>
        </div>
    </div>

    <!-- ============================================== -->
    <!-- DIALOG MODALS -->
    <!-- ============================================== -->

    <!-- Asset Registration Modal -->
    <div class="modal-backdrop hidden" id="modal-register-asset">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Register New Corporate Asset</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="form-register-asset">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="reg-name">Asset Name</label>
                        <input type="text" id="reg-name" required placeholder="e.g. MacBook Pro M3">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reg-category">Category</label>
                            <select id="reg-category" required>
                                <option value="">Select Category</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="reg-serial">Serial Number</label>
                            <input type="text" id="reg-serial" required placeholder="e.g. S192A0281F">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reg-date">Acquisition Date</label>
                            <input type="date" id="reg-date" required>
                        </div>
                        <div class="form-group">
                            <label for="reg-cost">Acquisition Cost ($)</label>
                            <input type="number" id="reg-cost" min="0" step="0.01" required placeholder="e.g. 2499.00">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="reg-condition">Initial Condition</label>
                            <select id="reg-condition">
                                <option value="New">New</option>
                                <option value="Good" selected>Good</option>
                                <option value="Fair">Fair</option>
                                <option value="Poor">Poor</option>
                                <option value="Damaged">Damaged</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="reg-location">Location</label>
                            <input type="text" id="reg-location" required placeholder="e.g. Bangalore Office, Floor 3">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:16px;">
                        <label for="reg-photo">Photo URL / Demo Image Path</label>
                        <input type="text" id="reg-photo" placeholder="e.g. img/macbook.jpg or any online URL">
                    </div>
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="reg-shared">
                        <label for="reg-shared">Mark as shared bookable resource (e.g. conference room, pool car)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Asset</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Allocate Asset Modal -->
    <div class="modal-backdrop hidden" id="modal-allocate-asset">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Allocate Asset</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="form-allocate-asset">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="alloc-asset-id">Asset to Allocate</label>
                        <select id="alloc-asset-id" required>
                            <option value="">Select Available Asset</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Allocation Target Type</label>
                        <div class="radio-group" style="margin-top: 6px;">
                            <label><input type="radio" name="alloc-target" value="employee" checked> Employee</label>
                            <label style="margin-left: 20px;"><input type="radio" name="alloc-target" value="department"> Department</label>
                        </div>
                    </div>
                    <div class="form-group" id="alloc-employee-group">
                        <label for="alloc-employee-id">Allocate to Employee</label>
                        <select id="alloc-employee-id">
                            <option value="">Select Employee</option>
                        </select>
                    </div>
                    <div class="form-group hidden" id="alloc-department-group">
                        <label for="alloc-department-id">Allocate to Department</label>
                        <select id="alloc-department-id">
                            <option value="">Select Department</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="alloc-return-date">Expected Return Date (Optional)</label>
                        <input type="date" id="alloc-return-date">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">Allocate</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Return/Check-in Asset Modal -->
    <div class="modal-backdrop hidden" id="modal-return-asset">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Asset Return Check-in</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="form-return-asset">
                <input type="hidden" id="return-alloc-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="return-condition">Condition on Return</label>
                        <select id="return-condition" required>
                            <option value="New">New</option>
                            <option value="Good" selected>Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Poor">Poor</option>
                            <option value="Damaged">Damaged</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="return-notes">Check-in Notes</label>
                        <textarea id="return-notes" rows="3" required placeholder="Describe any scratches, missing parts, or updates here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">Approve Check-in</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Request Transfer Modal -->
    <div class="modal-backdrop hidden" id="modal-request-transfer">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Request Asset Transfer</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="form-request-transfer">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="trans-asset-tag">Asset Tag Code</label>
                        <input type="text" id="trans-asset-tag" required placeholder="e.g. AF-0001">
                    </div>
                    <div class="form-group">
                        <label>Transfer Recipient Type</label>
                        <div class="radio-group" style="margin-top: 6px;">
                            <label><input type="radio" name="trans-target" value="employee" checked> Employee</label>
                            <label style="margin-left: 20px;"><input type="radio" name="trans-target" value="department"> Department</label>
                        </div>
                    </div>
                    <div class="form-group" id="trans-employee-group">
                        <label for="trans-employee-id">Transfer to Employee</label>
                        <select id="trans-employee-id">
                            <option value="">Select Employee</option>
                        </select>
                    </div>
                    <div class="form-group hidden" id="trans-department-group">
                        <label for="trans-department-id">Transfer to Department</label>
                        <select id="trans-department-id">
                            <option value="">Select Department</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Book Resource Modal -->
    <div class="modal-backdrop hidden" id="modal-book-resource">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Book Shared Resource</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="form-book-resource">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="book-asset-id">Shared Asset</label>
                        <select id="book-asset-id" required>
                            <option value="">Select Room / Vehicle / Table</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="book-date">Booking Date</label>
                        <input type="date" id="book-date" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="book-start">Start Time</label>
                            <input type="time" id="book-start" required>
                        </div>
                        <div class="form-group">
                            <label for="book-end">End Time</label>
                            <input type="time" id="book-end" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">Reserve Slot</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Raise Maintenance Modal -->
    <div class="modal-backdrop hidden" id="modal-raise-maint">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Raise Maintenance Ticket</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="form-raise-maint">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="maint-asset-id">Select Asset</label>
                        <select id="maint-asset-id" required>
                            <option value="">Select Asset</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="maint-priority">Priority</label>
                        <select id="maint-priority" required>
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="maint-desc">Describe Issue</label>
                        <textarea id="maint-desc" rows="3" required placeholder="Detail the fault or parts needed..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">File Ticket</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign Technician / Resolve Maintenance Modal -->
    <div class="modal-backdrop hidden" id="modal-update-maint">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Manage Maintenance Task</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="form-update-maint">
                <input type="hidden" id="update-maint-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="maint-state-select">Progress Status</label>
                        <select id="maint-state-select" required>
                            <option value="Technician Assigned">Technician Assigned</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Resolved">Resolved</option>
                        </select>
                    </div>
                    <div class="form-group" id="maint-tech-input-group">
                        <label for="maint-tech-name">Assigned Technician</label>
                        <input type="text" id="maint-tech-name" placeholder="e.g. John Doe Tech">
                    </div>
                    <div class="form-group">
                        <label for="maint-notes-area">Technician Notes</label>
                        <textarea id="maint-notes-area" rows="3" required placeholder="Write details about the repair/status update..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Task</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Audit Cycle Modal -->
    <div class="modal-backdrop hidden" id="modal-create-audit">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Initiate Verification Audit Cycle</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form id="form-create-audit">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="audit-name">Audit Cycle Name</label>
                        <input type="text" id="audit-name" required placeholder="e.g. Q3 Equipment Audit">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="audit-department-id">Scope: Department</label>
                            <select id="audit-department-id">
                                <option value="">All Departments</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="audit-location">Scope: Location</label>
                            <input type="text" id="audit-location" placeholder="e.g. Bangalore Office">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="audit-start">Start Date</label>
                            <input type="date" id="audit-start" required>
                        </div>
                        <div class="form-group">
                            <label for="audit-end">End Date</label>
                            <input type="date" id="audit-end" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="audit-auditors-multiselect">Assign Auditors (Hold Ctrl to select multiple)</label>
                        <select id="audit-auditors-multiselect" multiple required style="height: 100px;">
                            <!-- Loaded dynamically -->
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary">Start Audit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Detail Drawer / Modal for Assets History -->
    <div class="modal-backdrop hidden" id="modal-asset-detail">
        <div class="modal-card modal-lg">
            <div class="modal-header">
                <h3 id="detail-title">Loading...</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body asset-detail-body">
                <div class="detail-split">
                    <!-- Left Column -->
                    <div class="detail-col-specs">
                        <div class="detail-qr-block">
                            <div class="qr-code-placeholder" id="detail-qr-code"></div>
                            <span class="qr-label" id="detail-tag-label">AF-0000</span>
                        </div>
                        
                        <div class="specs-list" id="detail-specs-list">
                            <!-- Populated dynamically -->
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div class="detail-col-history">
                        <div class="history-tab-group">
                            <button class="history-tab-btn active" data-histtab="histtab-allocations">Allocation History</button>
                            <button class="history-tab-btn" data-histtab="histtab-maintenance">Maintenance History</button>
                        </div>
                        
                        <div class="history-tab-contents">
                            <div id="histtab-allocations" class="history-pane active">
                                <ul class="timeline" id="detail-allocations-timeline"></ul>
                            </div>
                            <div id="histtab-maintenance" class="history-pane">
                                <ul class="timeline" id="detail-maintenance-timeline"></ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div id="detail-footer-actions"></div>
                <button type="button" class="btn btn-secondary modal-close-btn">Close</button>
            </div>
        </div>
    </div>

    <!-- QR Scanner Modal -->
    <div id="modal-qr-scanner" class="modal-backdrop hidden">
        <div class="modal-card" style="max-width: 450px;">
            <div class="modal-header">
                <h3>📷 Scan Asset QR Code</h3>
                <button class="modal-close" id="btn-close-qr-reader">&times;</button>
            </div>
            <div class="modal-body" style="text-align: center;">
                <p style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:12px;">Position the asset QR code in front of your camera.</p>
                <div id="qr-reader" style="width: 100%; max-width: 380px; margin: 0 auto; background: #0b0f19; border: 2px dashed var(--border-color); border-radius: var(--radius); overflow: hidden;"></div>
                <div id="qr-reader-results" style="margin-top: 12px; font-weight:600; color:var(--color-success);"></div>
            </div>
            <div class="modal-footer" style="justify-content: center;">
                <button type="button" class="btn btn-secondary" id="btn-stop-qr">Close Scanner</button>
            </div>
        </div>
    </div>

    <!-- html5-qrcode CDN Script -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <!-- Application Script -->
    <script src="js/app.js"></script>
</body>
</html>

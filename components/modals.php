<?php
/**
 * JRC Dental Care - Modals Component
 * Contains all modals used throughout the website
 */
?>

<!-- Appointment Modal -->
<div class="modal fade" id="appointmentModal" tabindex="-1" aria-labelledby="appointmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-lg border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="appointmentModalLabel">Book an Appointment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="appointmentForm" action="process-appointment.php" method="post" data-validate="true">
                    <div class="mb-3">
                        <label for="service_type" class="form-label">Service Type</label>
                        <select class="form-select" id="service_type" name="service_type" required>
                            <option value="" selected disabled>Select a service</option>
                            <option value="General Check-up">General Check-up</option>
                            <option value="Teeth Cleaning">Teeth Cleaning</option>
                            <option value="Teeth Whitening">Teeth Whitening</option>
                            <option value="Dental Fillings">Dental Fillings</option>
                            <option value="Root Canal">Root Canal</option>
                            <option value="Orthodontics">Orthodontics</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="appointment_date" class="form-label">Preferred Date</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-calendar"></i></span>
                                <input type="date" class="form-control" id="appointment_date" name="appointment_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="appointment_time" class="form-label">Preferred Time</label>
                            <select class="form-select" id="appointment_time" name="appointment_time" required>
                                <option value="">Select Time</option>
                                <!-- Time slots will be populated by JavaScript -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="appointment_notes" class="form-label">Additional Notes (Optional)</label>
                        <textarea class="form-control" id="appointment_notes" name="appointment_notes" rows="3" placeholder="Please let us know if you have any specific concerns or requirements"></textarea>
                    </div>
                    
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="appointment_terms" name="appointment_terms" required>
                        <label class="form-check-label" for="appointment_terms">
                            I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">appointment policy</a>
                        </label>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary py-2">
                            <i class="fas fa-calendar-check me-2"></i> Book Appointment
                        </button>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <p class="small text-muted mb-0">
                    <i class="fas fa-info-circle me-1"></i> We'll confirm your appointment via email or phone
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Terms Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Terms of Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Welcome to JRC Dental Care! These terms and conditions outline the rules and regulations for the use of our services.</p>
                <!-- Add terms content here -->
                <h6>1. Acceptance of Terms</h6>
                <p>By accessing or using our services, you agree to be bound by these Terms and Conditions.</p>
                
                <h6>2. User Accounts</h6>
                <p>When you create an account with us, you must provide information that is accurate, complete, and current at all times.</p>
                
                <h6>3. Privacy</h6>
                <p>Your use of our services is also governed by our Privacy Policy.</p>
                
                <h6>4. Termination</h6>
                <p>We may terminate or suspend your account immediately, without prior notice or liability, for any reason.</p>
                
                <h6>5. Appointment Policy</h6>
                <p>Please provide at least 24 hours notice if you need to cancel or reschedule an appointment. Late cancellations or missed appointments may be subject to a fee.</p>
                
                <h6>6. Insurance and Payment</h6>
                <p>Patients are responsible for understanding their insurance coverage. Payment is expected at the time of service unless other arrangements have been made.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
            </div>
        </div>
    </div>
</div>

<!-- Privacy Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="privacyModalLabel">Privacy Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>At JRC Dental Care, we take your privacy seriously. This Privacy Policy describes how we collect, use, and share your information.</p>
                <!-- Add privacy content here -->
                <h6>1. Information We Collect</h6>
                <p>We collect information you provide directly to us when you register for an account, schedule an appointment, or contact us.</p>
                
                <h6>2. How We Use Your Information</h6>
                <p>We use the information we collect to provide, maintain, and improve our services, communicate with you, and for other legitimate purposes.</p>
                
                <h6>3. Information Sharing</h6>
                <p>We may share your information with third parties only for the purposes described in this Privacy Policy or as required by law.</p>

                <h6>4. Data Retention</h6>
                <p>Your personal data will be retained for as long as necessary to fulfill the purposes outlined in this Privacy Policy, unless a longer retention period is required or permitted by law.</p>

                <h6>5. Security</h6>
                <p>We take appropriate technical and organizational measures to protect your personal data against unauthorized access, disclosure, alteration, and destruction.</p>

                <h6>6. Your Rights</h6>
                <p>You have the right to access, correct, and delete your personal data, as well as other rights under applicable privacy laws.</p>

                <h6>7. Changes to This Policy</h6>
                <p>We may update our Privacy Policy from time to time. We will notify you of any significant changes by posting the new policy on this page.</p>

                <h6>8. Contact Us</h6>
                <p>If you have any questions about this Privacy Policy, please contact us at support@jrcdentalcare.com.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
            </div>
        </div>
    </div>
</div>

<!-- Search Modal -->
<div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="searchModalLabel">Search Our Website</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="searchForm" action="search-results.php" method="get">
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-light"><i class="fas fa-search"></i></span>
                        <input type="search" class="form-control" placeholder="What are you looking for?" name="q" required>
                        <button class="btn btn-primary" type="submit">Search</button>
                    </div>
                </form>
                
                <div class="mt-4">
                    <h6 class="mb-3">Popular Searches:</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="search-results.php?q=teeth+whitening" class="badge bg-light text-dark text-decoration-none py-2 px-3">Teeth Whitening</a>
                        <a href="search-results.php?q=dental+implants" class="badge bg-light text-dark text-decoration-none py-2 px-3">Dental Implants</a>
                        <a href="search-results.php?q=braces" class="badge bg-light text-dark text-decoration-none py-2 px-3">Braces</a>
                        <a href="search-results.php?q=emergency" class="badge bg-light text-dark text-decoration-none py-2 px-3">Emergency Care</a>
                        <a href="search-results.php?q=insurance" class="badge bg-light text-dark text-decoration-none py-2 px-3">Insurance</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal (general purpose) -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body text-center p-5">
                <div class="mb-4">
                    <i class="fas fa-check-circle text-success fa-5x"></i>
                </div>
                <h3 class="modal-title mb-3" id="successModalTitle">Success!</h3>
                <p class="mb-4" id="successModalMessage">Your request has been processed successfully.</p>
                <button type="button" class="btn btn-primary px-4 py-2" data-bs-dismiss="modal">Continue</button>
            </div>
        </div>
    </div>
</div>

<!-- Error Modal (general purpose) -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body text-center p-5">
                <div class="mb-4">
                    <i class="fas fa-exclamation-circle text-danger fa-5x"></i>
                </div>
                <h3 class="modal-title mb-3" id="errorModalTitle">Error</h3>
                <p class="mb-4" id="errorModalMessage">An error occurred while processing your request.</p>
                <button type="button" class="btn btn-primary px-4 py-2" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal (general purpose) -->
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body text-center p-5">
                <div class="mb-4">
                    <i class="fas fa-question-circle text-primary fa-5x"></i>
                </div>
                <h3 class="modal-title mb-3" id="confirmationModalTitle">Confirmation</h3>
                <p class="mb-4" id="confirmationModalMessage">Are you sure you want to proceed with this action?</p>
                <div class="d-flex justify-content-center gap-3">
                    <button type="button" class="btn btn-outline-secondary px-4 py-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary px-4 py-2" id="confirmActionBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Confirmation modal helper function
function showConfirmation(title, message, callback) {
    const modal = document.getElementById('confirmationModal');
    const modalInstance = new bootstrap.Modal(modal);
    
    document.getElementById('confirmationModalTitle').textContent = title;
    document.getElementById('confirmationModalMessage').textContent = message;
    
    const confirmBtn = document.getElementById('confirmActionBtn');
    
    // Remove previous event listeners
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    
    // Add new event listener
    newConfirmBtn.addEventListener('click', function() {
        modalInstance.hide();
        if (typeof callback === 'function') {
            callback();
        }
    });
    
    modalInstance.show();
}

// Success modal helper function
function showSuccess(title, message) {
    const modal = document.getElementById('successModal');
    const modalInstance = new bootstrap.Modal(modal);
    
    document.getElementById('successModalTitle').textContent = title;
    document.getElementById('successModalMessage').textContent = message;
    
    modalInstance.show();
}

// Error modal helper function
function showError(title, message) {
    const modal = document.getElementById('errorModal');
    const modalInstance = new bootstrap.Modal(modal);
    
    document.getElementById('errorModalTitle').textContent = title;
    document.getElementById('errorModalMessage').textContent = message;
    
    modalInstance.show();
}
</script>
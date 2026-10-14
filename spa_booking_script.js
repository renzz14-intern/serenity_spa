// Spa Booking System JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            if (document.querySelector('.booking-container')) {
                console.log('Initializing booking system...');
                window.spaBooking = new SpaBookingSystem();
            } else {
                console.log('Booking container not found, skipping initialization');
            }
        });
        
        class SpaBookingSystem {
            constructor() {
                this.currentStep = 1;
                this.totalSteps = 4;
                this.bookingData = {
                    healthInfo: {
                        medicalConditions: [],
                        pregnancy: '',
                        pregnancyWeeks: '',
                        allergies: [],
                        otherAllergy: '',
                        currentlyIll: ''
                    },
                    service: {},
                    roomType: '',
                    dateTime: {},
                    therapist: {},
                    personalInfo: {},
                    paymentMethod: '',
                    totalPrice: 0
                };
                
                this.init();
            }

            init() {
                this.setupEventListeners();
                this.setupDatePicker();
                this.generateTimeSlots();
                this.updateProgressTabs();
                this.setupHealthCheckLogic();
            }

            setupHealthCheckLogic() {
                // Handle "None" checkbox exclusivity for medical conditions
                const noneCondition = document.getElementById('no-conditions');
                const medicalCheckboxes = document.querySelectorAll('input[name="medicalConditions"]:not(#no-conditions)');
                
                noneCondition.addEventListener('change', () => {
                    if (noneCondition.checked) {
                        medicalCheckboxes.forEach(cb => cb.checked = false);
                    }
                });

                medicalCheckboxes.forEach(cb => {
                    cb.addEventListener('change', () => {
                        if (cb.checked) {
                            noneCondition.checked = false;
                        }
                        this.checkHealthWarnings();
                    });
                });

                // Handle "None" checkbox exclusivity for allergies
                const noneAllergy = document.getElementById('no-allergies');
                const allergyCheckboxes = document.querySelectorAll('input[name="allergies"]:not(#no-allergies)');
                
                noneAllergy.addEventListener('change', () => {
                    if (noneAllergy.checked) {
                        allergyCheckboxes.forEach(cb => cb.checked = false);
                        document.querySelector('.allergies-other').style.display = 'none';
                    }
                });

                allergyCheckboxes.forEach(cb => {
                    cb.addEventListener('change', () => {
                        if (cb.checked) {
                            noneAllergy.checked = false;
                        }
                    });
                });

                // Handle "Other" allergy text input
                const otherAllergyCheckbox = document.getElementById('other-allergy');
                const otherAllergyText = document.querySelector('.allergies-other');
                
                otherAllergyCheckbox.addEventListener('change', () => {
                    otherAllergyText.style.display = otherAllergyCheckbox.checked ? 'block' : 'none';
                });

                // Handle pregnancy weeks input
                const pregnantYes = document.getElementById('pregnant-yes');
                const pregnancyWeeks = document.querySelector('.pregnancy-weeks');
                
                pregnantYes.addEventListener('change', () => {
                    pregnancyWeeks.style.display = pregnantYes.checked ? 'block' : 'none';
                    this.checkHealthWarnings();
                });

                document.getElementById('pregnant-no').addEventListener('change', () => {
                    pregnancyWeeks.style.display = 'none';
                });

                // Handle illness check
                document.getElementById('sick-yes').addEventListener('change', () => {
                    this.checkHealthWarnings();
                });

                document.getElementById('sick-no').addEventListener('change', () => {
                    this.checkHealthWarnings();
                });
            }

            checkHealthWarnings() {
                const warning = document.getElementById('health-warning');
                let showWarning = false;

                // Check for conditions that require warning
                const heartCondition = document.getElementById('heart-condition').checked;
                const diabetes = document.getElementById('diabetes').checked;
                const surgery = document.getElementById('surgery-injury').checked;
                const skinInfection = document.getElementById('skin-infection').checked;
                const pregnant = document.getElementById('pregnant-yes').checked;
                const sick = document.getElementById('sick-yes').checked;

                if (heartCondition || diabetes || surgery || skinInfection || pregnant || sick) {
                    showWarning = true;
                }

                warning.classList.toggle('show', showWarning);
            }

            setupEventListeners() {
                // Navigation buttons
                document.querySelectorAll('.next-step').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const nextStep = parseInt(e.target.dataset.next);
                        if (this.validateCurrentStep()) {
                            this.goToStep(nextStep);
                        }
                    });
                });

                document.querySelectorAll('.prev-step').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const prevStep = parseInt(e.target.dataset.prev);
                        this.goToStep(prevStep);
                    });
                });

                // Confirm booking button
                const confirmBookingBtn = document.getElementById('confirm-booking-btn');
                if (confirmBookingBtn) {
                    confirmBookingBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        if (this.validateCurrentStep()) {
                            this.confirmBooking();
                        }
                    });
                }

                // Tab navigation
                document.querySelectorAll('.tab').forEach(tab => {
                    tab.addEventListener('click', (e) => {
                        const stepNumber = parseInt(tab.dataset.step);
                        if (stepNumber <= this.currentStep || stepNumber === this.currentStep + 1) {
                            if (stepNumber === this.currentStep + 1) {
                                if (this.validateCurrentStep()) {
                                    this.goToStep(stepNumber);
                                }
                            } else {
                                this.goToStep(stepNumber);
                            }
                        }
                    });
                });

                // Service selection
                document.querySelectorAll('.service-item input[name="service"]').forEach(radio => {
                    radio.addEventListener('change', () => {
                        this.handleServiceSelection(radio);
                        this.updateBookingSummary();
                    });
                });

                // Room type selection
                document.querySelectorAll('.service-item input[name="roomType"]').forEach(radio => {
                    radio.addEventListener('change', () => {
                        this.handleRoomSelection(radio);
                        this.updateBookingSummary();
                    });
                });

                // Therapist selection
                document.querySelectorAll('input[name="therapist"]').forEach(radio => {
                    radio.addEventListener('change', () => {
                        this.handleTherapistSelection(radio);
                    });
                });

                // Payment method selection
                document.querySelectorAll('input[name="paymentMethod"]').forEach(radio => {
                    radio.addEventListener('change', () => {
                        this.handlePaymentMethodSelection(radio);
                    });
                });

                // Date selection
                document.getElementById('booking-date').addEventListener('change', (e) => {
                    this.handleDateChange(e.target.value);
                });

                this.setupFormValidation();
            }

            setupFormValidation() {
                const inputs = document.querySelectorAll('input[required]');
                inputs.forEach(input => {
                    input.addEventListener('blur', () => this.validateInput(input));
                    input.addEventListener('input', () => this.clearValidationError(input));
                });
            }

            validateInput(input) {
                const value = input.value.trim();
                let isValid = true;
                let errorMessage = '';

                this.clearValidationError(input);

                if (!value && input.required) {
                    isValid = false;
                    errorMessage = 'This field is required';
                } else if (input.type === 'email' && value) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(value)) {
                        isValid = false;
                        errorMessage = 'Please enter a valid email address';
                    }
                } else if (input.type === 'tel' && value) {
                    const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
                    if (!phoneRegex.test(value.replace(/[\s\-\(\)]/g, ''))) {
                        isValid = false;
                        errorMessage = 'Please enter a valid phone number';
                    }
                }

                if (!isValid) {
                    this.showValidationError(input, errorMessage);
                }

                return isValid;
            }

            showValidationError(input, message) {
                input.style.borderColor = '#dc3545';
                input.style.backgroundColor = '#fff5f5';
                
                const existingError = input.parentNode.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }

                const errorElement = document.createElement('div');
                errorElement.className = 'error-message';
                errorElement.style.color = '#dc3545';
                errorElement.style.fontSize = '0.875rem';
                errorElement.style.marginTop = '0.25rem';
                errorElement.textContent = message;
                input.parentNode.appendChild(errorElement);
            }

            clearValidationError(input) {
                input.style.borderColor = '#e1e5e9';
                input.style.backgroundColor = '';
                
                const errorMessage = input.parentNode.querySelector('.error-message');
                if (errorMessage) {
                    errorMessage.remove();
                }
            }

            validateCurrentStep() {
                let isValid = true;

                switch (this.currentStep) {
                    case 1:
                        // Validate health check
                        this.collectHealthInfo();
                        
                        // Validate service and room selection
                        const selectedService = document.querySelector('input[name="service"]:checked');
                        const selectedRoom = document.querySelector('input[name="roomType"]:checked');
                        
                        if (!selectedService) {
                            this.showAlert('Please select a service', 'error');
                            isValid = false;
                        }
                        if (!selectedRoom) {
                            this.showAlert('Please select a room type', 'error');
                            isValid = false;
                        }
                        break;

                    case 2:
                        // Validate date, time and therapist
                        const selectedDate = document.getElementById('booking-date').value;
                        const selectedTime = document.querySelector('.time-slot.selected');
                        const selectedTherapist = document.querySelector('input[name="therapist"]:checked');

                        if (!selectedDate) {
                            this.showAlert('Please select a date', 'error');
                            isValid = false;
                        }
                        if (!selectedTime) {
                            this.showAlert('Please select a time slot', 'error');
                            isValid = false;
                        }
                        if (!selectedTherapist) {
                            this.showAlert('Please select a therapist preference', 'error');
                            isValid = false;
                        }

                        if (isValid) {
                            this.bookingData.dateTime = {
                                date: selectedDate,
                                time: selectedTime.textContent,
                                formattedDate: this.formatDate(selectedDate)
                            };
                        }
                        break;

                    case 3:
                        // Validate personal info and payment method
                        if (!document.querySelector('.payment-item input[name="paymentMethod"]:checked')) {
                            this.showAlert('Please select a payment method', 'error');
                            isValid = false;
                        }

                        // Check if user is logged in, if not validate personal info fields
                        if (!<?php echo $isLoggedIn ? 'true' : 'false'; ?>) {
                            const requiredFields = ['full-name', 'email', 'phone'];
                            requiredFields.forEach(fieldId => {
                                const input = document.getElementById(fieldId);
                                if (input && !input.value.trim()) {
                                    this.showValidationError(input, 'This field is required');
                                    isValid = false;
                                }
                            });
                        }

                        if (isValid) {
                            // Get personal info from hidden fields if logged in, otherwise from form
                            this.bookingData.personalInfo = {
                                fullName: document.getElementById('full-name').value.trim(),
                                email: document.getElementById('email').value.trim(),
                                phone: document.getElementById('phone').value.trim()
                            };
                            this.bookingData.paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
                        }
                        break;
                }

                return isValid;
            }

            collectHealthInfo() {
                // Collect medical conditions
                const medicalConditions = [];
                document.querySelectorAll('input[name="medicalConditions"]:checked').forEach(cb => {
                    medicalConditions.push(cb.value);
                });

                // Collect pregnancy info
                const pregnancyRadio = document.querySelector('input[name="pregnancy"]:checked');
                const pregnancyWeeks = document.getElementById('pregnancy-weeks').value;

                // Collect allergies
                const allergies = [];
                document.querySelectorAll('input[name="allergies"]:checked').forEach(cb => {
                    allergies.push(cb.value);
                });
                const otherAllergy = document.getElementById('other-allergy-text').value;

                // Collect current illness status
                const currentlyIllRadio = document.querySelector('input[name="currentlyIll"]:checked');

                this.bookingData.healthInfo = {
                    medicalConditions: medicalConditions,
                    pregnancy: pregnancyRadio ? pregnancyRadio.value : '',
                    pregnancyWeeks: pregnancyWeeks,
                    allergies: allergies,
                    otherAllergy: otherAllergy,
                    currentlyIll: currentlyIllRadio ? currentlyIllRadio.value : ''
                };
            }

            goToStep(stepNumber) {
                if (stepNumber < 1 || stepNumber > this.totalSteps) return;

                // Hide current step
                const currentStepElement = document.getElementById(`step-${this.currentStep}`);
                if (currentStepElement) {
                    currentStepElement.classList.remove('active');
                }

                // Show new step
                const newStepElement = document.getElementById(`step-${stepNumber}`);
                if (newStepElement) {
                    newStepElement.classList.add('active');
                }

                // Update current step
                this.currentStep = stepNumber;

                // Update progress tabs
                this.updateProgressTabs();

                // Update final summary if on step 4
                if (stepNumber === 4) {
                    this.updateFinalSummary();
                }

                // Scroll to top of form
                document.querySelector('.booking-container').scrollIntoView({ behavior: 'smooth' });
            }

            updateProgressTabs() {
                document.querySelectorAll('.tab').forEach(tab => {
                    const stepNumber = parseInt(tab.dataset.step);
                    tab.classList.remove('active', 'completed');

                    if (stepNumber < this.currentStep) {
                        tab.classList.add('completed');
                    } else if (stepNumber === this.currentStep) {
                        tab.classList.add('active');
                    }
                });
            }

            handleServiceSelection(radio) {
                // Remove selection styling from all service items
                document.querySelectorAll('input[name="service"]').forEach(r => {
                    r.closest('.service-item').classList.remove('selected');
                });

                // Add selection styling to the selected item
                const serviceItem = radio.closest('.service-item');
                if (serviceItem) {
                    serviceItem.classList.add('selected');
                    
                    const serviceName = serviceItem.querySelector('h3').textContent;
                    const servicePrice = parseFloat(serviceItem.dataset.price);
                    const serviceDuration = parseInt(serviceItem.dataset.duration);

                    this.bookingData.service = {
                        type: serviceName.toLowerCase().replace(/\s+/g, '_'),
                        name: serviceName,
                        price: servicePrice,
                        duration: serviceDuration
                    };
                }
            }

            handleRoomSelection(radio) {
                // Remove selection styling from all room items
                document.querySelectorAll('input[name="roomType"]').forEach(r => {
                    r.closest('.service-item').classList.remove('selected');
                });

                // Add selection styling to the selected item
                const roomItem = radio.closest('.service-item');
                if (roomItem) {
                    roomItem.classList.add('selected');
                }
                
                this.bookingData.roomType = radio.value;
            }

            handleTherapistSelection(radio) {
                const therapistItem = radio.closest('.service-item');
                document.querySelectorAll('input[name="therapist"]').forEach(item => {
                    item.closest('.service-item').classList.remove('selected');
                });
                therapistItem.classList.add('selected');
                
                this.bookingData.therapist = {
                    id: radio.value,
                    name: radio.value === 'no-preference' ? 'No Preference' : therapistItem.querySelector('h3').textContent
                };
                this.updateBookingSummary();
            }

            handlePaymentMethodSelection(radio) {
                const paymentItem = radio.closest('.payment-item');
                document.querySelectorAll('input[name="paymentMethod"]').forEach(item => {
                    item.closest('.payment-item').classList.remove('selected');
                });
                paymentItem.classList.add('selected');
                this.bookingData.paymentMethod = radio.value;
            }

            setupDatePicker() {
                const dateInput = document.getElementById('booking-date');
                const today = new Date();
                const tomorrow = new Date(today);
                tomorrow.setDate(tomorrow.getDate() + 1);

                dateInput.min = tomorrow.toISOString().split('T')[0];

                const maxDate = new Date(today);
                maxDate.setMonth(maxDate.getMonth() + 3);
                dateInput.max = maxDate.toISOString().split('T')[0];
            }

            generateTimeSlots() {
                const timeSlotsContainer = document.getElementById('time-slots');
                if (!timeSlotsContainer) return;
                
                const timeSlots = [
                    '9:00 AM', '9:30 AM', '10:00 AM', '10:30 AM',
                    '11:00 AM', '11:30 AM', '1:00 PM', '1:30 PM',
                    '2:00 PM', '2:30 PM', '3:00 PM', '3:30 PM',
                    '4:00 PM', '4:30 PM', '5:00 PM', '5:30 PM',
                    '6:00 PM', '6:30 PM', '7:00 PM', '7:30 PM'
                ];

                timeSlotsContainer.innerHTML = '';

                timeSlots.forEach(time => {
                    const timeSlot = document.createElement('div');
                    timeSlot.className = 'time-slot';
                    timeSlot.textContent = time;
                    timeSlot.addEventListener('click', () => this.selectTimeSlot(timeSlot));
                    timeSlotsContainer.appendChild(timeSlot);
                });
            }

            selectTimeSlot(timeSlot) {
                if (timeSlot.classList.contains('unavailable')) return;

                document.querySelectorAll('.time-slot').forEach(slot => {
                    slot.classList.remove('selected');
                });

                timeSlot.classList.add('selected');
            }

            convertTo12Hour(time24) {
    // Convert 24-hour time (e.g., "14:30:00") to 12-hour format (e.g., "2:30 PM")
    if (!time24) return '';
    
    const [hours, minutes] = time24.split(':');
    const hour12 = parseInt(hours) % 12 || 12;
    const period = parseInt(hours) >= 12 ? 'PM' : 'AM';
    const minuteStr = minutes === '00' ? '' : `:${minutes}`;
    return `${hour12}${minuteStr} ${period}`;
}

            async handleDateChange(selectedDate) {
                if (!selectedDate) return;
                
                // Reset time slot selection
                document.querySelectorAll('.time-slot').forEach(slot => {
                    slot.classList.remove('selected', 'unavailable');
                });
                
                // Fetch booked time slots for the selected date
                await this.updateTimeSlotAvailability(selectedDate);
                this.updateBookingSummary();
            }

            async updateTimeSlotAvailability(date) {
    try {
        // Fetch booked appointments for the selected date
        const response = await fetch('check_availability.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ date: date })
        });
        
        if (!response.ok) {
            console.error('Server returned error:', response.status, response.statusText);
            // Don't mark any slots as unavailable if server error
            return;
        }
        
        const responseText = await response.text();
        console.log('Server response:', responseText);
        
        let bookedSlots;
        try {
            bookedSlots = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Failed to parse server response:', parseError);
            console.error('Response was:', responseText);
            // Don't mark any slots as unavailable if JSON parse fails
            return;
        }
        
        // Ensure bookedSlots is an array
        if (!Array.isArray(bookedSlots)) {
            console.error('Expected array but received:', typeof bookedSlots, bookedSlots);
            // If server returned an error object instead of array
            if (bookedSlots && bookedSlots.error) {
                console.error('Server error:', bookedSlots.message);
                this.showAlert('Unable to check availability: ' + bookedSlots.message, 'error');
            }
            // Don't mark any slots as unavailable
            return;
        }
        
        // Mark booked time slots as unavailable
        const timeSlots = document.querySelectorAll('.time-slot');
        timeSlots.forEach(slot => {
            const slotTime = slot.textContent.trim();
            
            // Check if this time slot is booked
            const isBooked = bookedSlots.some(booking => {
                if (!booking || !booking.booking_time) {
                    console.warn('Invalid booking object:', booking);
                    return false;
                }
                return this.convertTo12Hour(booking.booking_time) === slotTime;
            });
            
            if (isBooked) {
                slot.classList.add('unavailable');
                slot.title = 'This time slot is not available';
            } else {
                slot.classList.remove('unavailable');
                slot.title = '';
            }
        });
        
    } catch (error) {
        console.error('Error fetching availability:', error);
        this.showAlert('Unable to check time slot availability. Please try selecting a different date.', 'error');
        // If there's a network error, don't mark any slots as unavailable
    }
}

// Also update the confirmBooking method to handle the same issue
async confirmBooking() {
    if (!this.validateCurrentStep()) {
        return;
    }

    this.showLoadingOverlay();

    try {
        // First, double-check availability before booking
        const availabilityCheck = await fetch('check_availability.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                date: this.bookingData.dateTime.date,
                time: this.bookingData.dateTime.time 
            })
        });
        
        if (availabilityCheck.ok) {
            const responseText = await availabilityCheck.text();
            
            let availabilityData;
            try {
                availabilityData = JSON.parse(responseText);
            } catch (parseError) {
                console.warn('Could not parse availability response, proceeding with booking');
                availabilityData = null;
            }
            
            // Only check availability if we got valid data
            if (availabilityData) {
                // If checking specific time availability
                if (availabilityData.hasOwnProperty('available')) {
                    if (!availabilityData.available || availabilityData.booked) {
                        throw new Error('Sorry, this time slot has just been booked by another customer. Please select a different time.');
                    }
                }
                // If checking all booked slots for the date
                else if (Array.isArray(availabilityData)) {
                    const isTimeSlotTaken = availabilityData.some(booking => {
                        if (!booking || !booking.booking_time) return false;
                        return this.convertTo12Hour(booking.booking_time) === this.bookingData.dateTime.time;
                    });
                    
                    if (isTimeSlotTaken) {
                        throw new Error('Sorry, this time slot has just been booked by another customer. Please select a different time.');
                    }
                }
            }
        } else {
            console.warn('Availability check failed, proceeding with booking attempt');
        }

        // Collect all final data including health information
        const bookingData = {
            fullName: this.bookingData.personalInfo.fullName,
            email: this.bookingData.personalInfo.email,
            phone: this.bookingData.personalInfo.phone,
            serviceType: this.bookingData.service.type,
            serviceName: this.bookingData.service.name,
            servicePrice: this.bookingData.service.price,
            serviceDuration: this.bookingData.service.duration,
            bookingDate: this.bookingData.dateTime.date,
            bookingTime: this.bookingData.dateTime.time,
            roomType: this.bookingData.roomType,
            therapistId: this.bookingData.therapist.id,
            paymentMethod: this.bookingData.paymentMethod,
            healthInfo: this.bookingData.healthInfo
        };

        console.log('Sending booking data:', bookingData);

        const response = await fetch('process_booking.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(bookingData)
        });

        const rawResponse = await response.text();
        console.log('Raw server response:', rawResponse);

        let result;
        try {
            result = JSON.parse(rawResponse);
        } catch (e) {
            console.error('Failed to parse server response:', rawResponse);
            throw new Error('Server returned invalid response. Please try again.');
        }

        if (!response.ok) {
            throw new Error(result.message || `Server returned error ${response.status}`);
        }
        
        if (result.success) {
            this.bookingData.reference = result.bookingReference;
            this.processBookingConfirmation(result.bookingReference);
            this.goToStep(4);
            this.showAlert('Booking confirmed successfully!', 'success');
        } else {
            throw new Error(result.message || 'Failed to process booking');
        }
        
    } catch (error) {
        console.error('Booking error:', error);
        this.showAlert(error.message || 'Error processing booking', 'error');
        
        // If there was a booking conflict, refresh the time slots
        if (error.message.includes('time slot has just been booked')) {
            await this.updateTimeSlotAvailability(this.bookingData.dateTime.date);
        }
    } finally {
        this.hideLoadingOverlay();
    }
}

            processBookingConfirmation(bookingReference) {
                const refElement = document.getElementById('booking-reference');
                const emailElement = document.getElementById('confirmation-email');
                
                if (refElement) {
                    refElement.textContent = bookingReference;
                }
                if (emailElement) {
                    emailElement.textContent = this.bookingData.personalInfo.email;
                }
            }

            updateFinalSummary() {
                document.getElementById('final-service').textContent = this.bookingData.service.name || '-';
                document.getElementById('final-room').textContent = this.getRoomDisplayName() || '-';
                document.getElementById('final-datetime').textContent = 
                    `${this.bookingData.dateTime.formattedDate} at ${this.bookingData.dateTime.time}` || '-';
                document.getElementById('final-therapist').textContent = this.bookingData.therapist.name || '-';
                document.getElementById('final-price').textContent = `${this.bookingData.totalPrice}`;
                
                // Update final health summary
                this.updateFinalHealthSummary();
            }

            updateFinalHealthSummary() {
                const healthInfo = this.bookingData.healthInfo;
                
                // Medical Conditions
                const finalMedicalElement = document.getElementById('final-medical-conditions');
                if (healthInfo.medicalConditions && healthInfo.medicalConditions.length > 0) {
                    const conditions = healthInfo.medicalConditions.map(condition => {
                        switch (condition) {
                            case 'heart': return 'Heart Condition / High Blood Pressure';
                            case 'diabetes': return 'Diabetes';
                            case 'surgery': return 'Recent Surgery / Injury';
                            case 'skin': return 'Skin Infection / Open Wounds';
                            case 'none': return 'None reported';
                            default: return condition;
                        }
                    });
                    finalMedicalElement.textContent = conditions.join(', ');
                } else {
                    finalMedicalElement.textContent = 'None reported';
                }

                // Pregnancy Status
                const finalPregnancyElement = document.getElementById('final-pregnancy');
                if (healthInfo.pregnancy === 'yes') {
                    const weeks = healthInfo.pregnancyWeeks ? ` (${healthInfo.pregnancyWeeks} weeks)` : '';
                    finalPregnancyElement.textContent = `Yes${weeks}`;
                } else if (healthInfo.pregnancy === 'no') {
                    finalPregnancyElement.textContent = 'No';
                } else {
                    finalPregnancyElement.textContent = 'Not specified';
                }

                // Allergies
                const finalAllergiesElement = document.getElementById('final-allergies');
                if (healthInfo.allergies && healthInfo.allergies.length > 0) {
                    const allergies = healthInfo.allergies.map(allergy => {
                        switch (allergy) {
                            case 'oils': return 'Massage Oils / Lotions';
                            case 'essential': return 'Essential Oils';
                            case 'other': return healthInfo.otherAllergy ? `Other: ${healthInfo.otherAllergy}` : 'Other (not specified)';
                            case 'none': return 'None reported';
                            default: return allergy;
                        }
                    });
                    finalAllergiesElement.textContent = allergies.join(', ');
                } else {
                    finalAllergiesElement.textContent = 'None reported';
                }

                // Current Illness
                const finalIllnessElement = document.getElementById('final-illness');
                if (healthInfo.currentlyIll === 'yes') {
                    finalIllnessElement.textContent = 'Currently experiencing illness symptoms';
                    finalIllnessElement.style.color = '#dc3545';
                } else if (healthInfo.currentlyIll === 'no') {
                    finalIllnessElement.textContent = 'No current illness reported';
                    finalIllnessElement.style.color = '#28a745';
                } else {
                    finalIllnessElement.textContent = 'Not specified';
                }
            }

            formatDate(dateString) {
                const date = new Date(dateString);
                const options = { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                };
                return date.toLocaleDateString('en-US', options);
            }

            showLoadingOverlay() {
                const overlay = document.getElementById('loading-overlay');
                if (overlay) {
                    overlay.style.display = 'flex';
                }
            }

            hideLoadingOverlay() {
                const overlay = document.getElementById('loading-overlay');
                if (overlay) {
                    overlay.style.display = 'none';
                }
            }

            showAlert(message, type = 'info') {
                const alert = document.createElement('div');
                alert.className = `alert alert-${type}`;
                alert.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 1rem 1.5rem;
                    border-radius: 8px;
                    color: white;
                    font-weight: 500;
                    z-index: 10000;
                    min-width: 300px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                    transform: translateX(100%);
                    transition: transform 0.3s ease;
                `;

                switch (type) {
                    case 'error':
                        alert.style.background = 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';
                        break;
                    case 'success':
                        alert.style.background = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
                        break;
                    default:
                        alert.style.background = 'linear-gradient(135deg, #17a2b8 0%, #138496 100%)';
                }

                alert.textContent = message;
                document.body.appendChild(alert);

                setTimeout(() => {
                    alert.style.transform = 'translateX(0)';
                }, 100);

                setTimeout(() => {
                    alert.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 300);
                }, 4000);
            }

            updateBookingSummary() {
                const selectedService = document.querySelector('input[name="service"]:checked');
                const selectedRoom = document.querySelector('input[name="roomType"]:checked');

                if (selectedService) {
                    document.getElementById('summary-service').textContent = this.bookingData.service.name;
                }

                if (selectedRoom) {
                    document.getElementById('summary-room').textContent = this.getRoomDisplayName();
                }

                if (this.bookingData.dateTime.date && this.bookingData.dateTime.time) {
                    document.getElementById('summary-datetime').textContent = 
                        `${this.bookingData.dateTime.formattedDate} at ${this.bookingData.dateTime.time}`;
                }

                if (this.bookingData.therapist.name) {
                    document.getElementById('summary-therapist').textContent = this.bookingData.therapist.name;
                }

                // Calculate total price
                this.calculateTotalPrice();
                document.getElementById('summary-price').textContent = `${this.bookingData.totalPrice}`;
                
                // Update health information summary
                this.updateHealthSummary();
            }

            updateHealthSummary() {
                // Ensure health info is collected
                if (!this.bookingData.healthInfo || Object.keys(this.bookingData.healthInfo).length === 0) {
                    this.collectHealthInfo();
                }

                const healthInfo = this.bookingData.healthInfo;
                
                // Medical Conditions
                const medicalElement = document.getElementById('summary-medical-conditions');
                if (healthInfo.medicalConditions && healthInfo.medicalConditions.length > 0) {
                    const conditions = healthInfo.medicalConditions.map(condition => {
                        switch (condition) {
                            case 'heart': return 'Heart Condition / High Blood Pressure';
                            case 'diabetes': return 'Diabetes';
                            case 'surgery': return 'Recent Surgery / Injury';
                            case 'skin': return 'Skin Infection / Open Wounds';
                            case 'none': return 'None reported';
                            default: return condition;
                        }
                    });
                    medicalElement.textContent = conditions.join(', ');
                } else {
                    medicalElement.textContent = 'None reported';
                }

                // Pregnancy Status
                const pregnancyElement = document.getElementById('summary-pregnancy');
                if (healthInfo.pregnancy === 'yes') {
                    const weeks = healthInfo.pregnancyWeeks ? ` (${healthInfo.pregnancyWeeks} weeks)` : '';
                    pregnancyElement.textContent = `Yes${weeks}`;
                } else if (healthInfo.pregnancy === 'no') {
                    pregnancyElement.textContent = 'No';
                } else {
                    pregnancyElement.textContent = 'Not specified';
                }

                // Allergies
                const allergiesElement = document.getElementById('summary-allergies');
                if (healthInfo.allergies && healthInfo.allergies.length > 0) {
                    const allergies = healthInfo.allergies.map(allergy => {
                        switch (allergy) {
                            case 'oils': return 'Massage Oils / Lotions';
                            case 'essential': return 'Essential Oils';
                            case 'other': return healthInfo.otherAllergy ? `Other: ${healthInfo.otherAllergy}` : 'Other (not specified)';
                            case 'none': return 'None reported';
                            default: return allergy;
                        }
                    });
                    allergiesElement.textContent = allergies.join(', ');
                } else {
                    allergiesElement.textContent = 'None reported';
                }

                // Current Illness
                const illnessElement = document.getElementById('summary-illness');
                if (healthInfo.currentlyIll === 'yes') {
                    illnessElement.textContent = 'Currently experiencing illness symptoms';
                    illnessElement.style.color = '#dc3545';
                    illnessElement.style.fontWeight = '600';
                } else if (healthInfo.currentlyIll === 'no') {
                    illnessElement.textContent = 'No current illness reported';
                    illnessElement.style.color = '#28a745';
                } else {
                    illnessElement.textContent = 'Not specified';
                }

                // Show health alert if needed
                this.showHealthAlert();
            }

            showHealthAlert() {
                const healthAlert = document.getElementById('health-alert');
                const healthInfo = this.bookingData.healthInfo;
                let showAlert = false;

                // Check for conditions that require special attention
                if (healthInfo.medicalConditions && healthInfo.medicalConditions.length > 0) {
                    const concerningConditions = ['heart', 'diabetes', 'surgery', 'skin'];
                    showAlert = healthInfo.medicalConditions.some(condition => concerningConditions.includes(condition));
                }

                if (healthInfo.pregnancy === 'yes') {
                    showAlert = true;
                }

                if (healthInfo.currentlyIll === 'yes') {
                    showAlert = true;
                }

                if (healthInfo.allergies && healthInfo.allergies.length > 0 && !healthInfo.allergies.includes('none')) {
                    showAlert = true;
                }

                healthAlert.style.display = showAlert ? 'block' : 'none';
            }

            getRoomDisplayName() {
                const roomNames = {
                    'single': 'Single Treatment Room',
                    'couples': 'Couples Room',
                    'group': 'Group Room',
                    'vip': 'VIP Suite'
                };
                return roomNames[this.bookingData.roomType] || '';
            }

            calculateTotalPrice() {
                let totalPrice = this.bookingData.service.price || 0;
                
                // Add room surcharge
                switch (this.bookingData.roomType) {
                    case 'single': totalPrice += 600; break;
                    case 'couples': totalPrice += 1000; break;
                    case 'group': totalPrice += 1750; break;
                    case 'vip': totalPrice += 2000; break;
                }
                
                this.bookingData.totalPrice = `₱${totalPrice.toLocaleString()}`;
            }
        }
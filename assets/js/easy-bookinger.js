/**
 * Easy Bookinger Calendar JavaScript
 */

(function($) {
    'use strict';
    
    window.EasyBookingerCalendar = {
        settings: {},
        selectedDates: [],
        currentDate: new Date(),
        bookedDates: {},
        
        init: function(options) {
            this.settings = $.extend({
                displayMonths: 3,
                maxSelectableDays: 5,
                allowedDays: [1, 2, 3, 4, 5],
                bookedDates: {},
                restrictedDates: [],
                quotasData: {},
                specialAvailability: {},
                enableTimeSlots: false,
                timeSlots: [],
                bookingFields: []
            }, options);
            
            this.bookedDates = this.settings.bookedDates;
            this.restrictedDates = this.settings.restrictedDates;
            this.quotasData = this.settings.quotasData;
            this.specialAvailability = this.settings.specialAvailability;
            this.bindEvents();
            this.renderCalendar();
        },
        
        bindEvents: function() {
            var self = this;
            
            // Navigation buttons
            $(document).on('click', '.eb-prev-month', function() {
                if (!self.canNavigatePrevious()) {
                    return false;
                }
                self.currentDate.setMonth(self.currentDate.getMonth() - 1);
                self.renderCalendar();
            });
            
            $(document).on('click', '.eb-next-month', function() {
                if (!self.canNavigateNext()) {
                    return false;
                }
                self.currentDate.setMonth(self.currentDate.getMonth() + 1);
                self.renderCalendar();
            });
            
            // Date selection
            $(document).on('click', '.eb-calendar-day.selectable', function() {
                var date = $(this).data('date');
                self.toggleDateSelection(date, $(this));
            });
            
            // Book button
            $(document).on('click', '#eb-book-button', function() {
                if (self.selectedDates.length > 0) {
                    self.showBookingForm();
                } else {
                    toastr.warning(easyBookinger.text.selectDate);
                }
            });
            
            // Modal events
            $(document).on('click', '.eb-modal-close', function() {
                self.closeModal();
            });
            
            $(document).on('click', '.eb-modal', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });
            
            $(document).on('click', '#eb-form-cancel', function() {
                self.closeModal();
            });
            
            // Form submission
            $(document).on('submit', '#eb-booking-form', function(e) {
                e.preventDefault();
                self.submitBooking();
            });
            
            // Remove selected date
            $(document).on('click', '.eb-selected-date-remove', function() {
                var date = $(this).closest('.eb-selected-date-item').data('date');
                self.removeSelectedDate(date);
            });
        },
        
        renderCalendar: function() {
            this.updateMonthDisplay();
            this.renderCalendarDays();
            this.updateSelectedDatesDisplay();
            this.updateBookButton();
            this.updateNavigationButtons();
        },
        
        canNavigatePrevious: function() {
            var today = new Date();
            var currentMonth = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1);
            var todayMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            
            // Don't allow navigation to past months
            return currentMonth > todayMonth;
        },
        
        canNavigateNext: function() {
            var today = new Date();
            var maxMonths = parseInt(this.settings.displayMonths) || 3;
            var maxDate = new Date(today.getFullYear(), today.getMonth() + maxMonths - 1, 1);
            var currentMonth = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth(), 1);
            
            // Don't allow navigation beyond display_months setting
            return currentMonth < maxDate;
        },
        
        updateNavigationButtons: function() {
            var $prevBtn = $('.eb-prev-month');
            var $nextBtn = $('.eb-next-month');
            
            // Update previous button
            if (this.canNavigatePrevious()) {
                $prevBtn.removeClass('disabled').prop('disabled', false);
            } else {
                $prevBtn.addClass('disabled').prop('disabled', true);
            }
            
            // Update next button
            if (this.canNavigateNext()) {
                $nextBtn.removeClass('disabled').prop('disabled', false);
            } else {
                $nextBtn.addClass('disabled').prop('disabled', true);
            }
        },
        
        updateMonthDisplay: function() {
            var monthNames = easyBookinger.monthNames;
            var monthText = this.currentDate.getFullYear() + '年' + monthNames[this.currentDate.getMonth()];
            $('#eb-current-month-text').text(monthText);
        },
        
        renderCalendarDays: function() {
            var $container = $('#eb-calendar-days');
            $container.empty();
            
            var year = this.currentDate.getFullYear();
            var month = this.currentDate.getMonth();
            var today = new Date();
            
            // Get first day of month and number of days
            var firstDay = new Date(year, month, 1);
            var lastDay = new Date(year, month + 1, 0);
            var daysInMonth = lastDay.getDate();
            var startDayOfWeek = firstDay.getDay();
            
            // Add empty cells for days before month starts
            for (var i = 0; i < startDayOfWeek; i++) {
                var prevMonthDay = new Date(year, month, -startDayOfWeek + i + 1);
                var $day = this.createDayElement(prevMonthDay, true);
                $container.append($day);
            }
            
            // Add days of current month
            for (var day = 1; day <= daysInMonth; day++) {
                var date = new Date(year, month, day);
                var $day = this.createDayElement(date, false);
                $container.append($day);
            }
            
            // Add days from next month to complete the grid
            var totalCells = $container.children().length;
            var remainingCells = Math.ceil(totalCells / 7) * 7 - totalCells;
            
            for (var i = 1; i <= remainingCells; i++) {
                var nextMonthDay = new Date(year, month + 1, i);
                var $day = this.createDayElement(nextMonthDay, true);
                $container.append($day);
            }
        },
        
        createDayElement: function(date, isOtherMonth) {
            var dateStr = this.formatDate(date);
            var dayOfWeek = date.getDay();
            var today = new Date();
            var isToday = this.isSameDate(date, today);
            var isPast = date < today;
            var isSelected = this.selectedDates.indexOf(dateStr) !== -1;
            var isBooked = this.bookedDates.hasOwnProperty(dateStr);
            var isRestricted = this.restrictedDates.indexOf(dateStr) !== -1;
            var isAllowedDay = this.settings.allowedDays.indexOf(dayOfWeek) !== -1;
            var hasSpecialAvailability = this.specialAvailability.hasOwnProperty(dateStr);
            var remainingQuota = this.quotasData[dateStr] || 0;
            var isQuotaFull = remainingQuota <= 0;
            
            // Check if same-day booking is disabled and this is today
            var isSameDayBlocked = isToday && this.settings.allowSameDayBooking === false;
            
            // Check if date is beyond display months range
            var maxDisplayDate = new Date(today);
            maxDisplayDate.setMonth(maxDisplayDate.getMonth() + this.settings.displayMonths);
            var isBeyondDisplayRange = date > maxDisplayDate;
            
            // Special availability overrides normal day restrictions
            var isSelectableDay = hasSpecialAvailability || isAllowedDay;
            
            var classes = ['eb-calendar-day'];
            
            if (isOtherMonth) {
                classes.push('other-month');
            } else if (isPast || !isSelectableDay || isRestricted || isQuotaFull || isBeyondDisplayRange || isSameDayBlocked) {
                classes.push('disabled');
                if (isRestricted) {
                    classes.push('restricted');
                }
                if (isQuotaFull) {
                    classes.push('quota-full');
                }
                if (isBeyondDisplayRange) {
                    classes.push('beyond-range');
                }
            } else {
                classes.push('selectable');
            }
            
            if (isToday) {
                classes.push('today');
            }
            
            if (isSelected) {
                classes.push('selected');
            }
            
            if (isBooked) {
                classes.push('booked');
            }
            
            if (hasSpecialAvailability) {
                classes.push('special-availability');
            }
            
            var statusText = '';
            if (isPast || isRestricted || isBeyondDisplayRange) {
                statusText = '<div class="eb-day-status unavailable">不可</div>';
            } else if (isSameDayBlocked) {
                statusText = '<div class="eb-day-status same-day-blocked">当日不可</div>';
            } else if (!isSelectableDay) {
                statusText = '<div class="eb-day-status not-allowed">不可</div>';
            } else if (isQuotaFull) {
                statusText = '<div class="eb-day-status quota-full">満杯</div>';
            } else if (remainingQuota > 0) {
                statusText = '<div class="eb-day-status available">残り' + remainingQuota + '件</div>';
            }
            
            // Add special availability indicator
            if (hasSpecialAvailability) {
                var specialData = this.specialAvailability[dateStr];
                var specialText = '臨時予約可';
                if (specialData.reason) {
                    specialText += '（' + specialData.reason + '）';
                }
                statusText += '<div class="eb-day-special">' + specialText + '</div>';
            }
            
            var $day = $('<div>')
                .addClass(classes.join(' '))
                .attr('data-date', dateStr)
                .html(
                    '<div class="eb-day-number">' + date.getDate() + '</div>' +
                    statusText
                );
            
            return $day;
        },
        
        toggleDateSelection: function(dateStr, $element) {
            var index = this.selectedDates.indexOf(dateStr);
            
            if (index !== -1) {
                // Remove from selection
                this.selectedDates.splice(index, 1);
                $element.removeClass('selected');
            } else {
                // Check maximum selection limit
                if (this.selectedDates.length >= this.settings.maxSelectableDays) {
                    toastr.warning(easyBookinger.text.maxDaysExceeded);
                    return;
                }
                
                // Add to selection
                this.selectedDates.push(dateStr);
                $element.addClass('selected');
            }
            
            this.updateSelectedDatesDisplay();
            this.updateBookButton();
        },
        
        removeSelectedDate: function(dateStr) {
            var index = this.selectedDates.indexOf(dateStr);
            if (index !== -1) {
                this.selectedDates.splice(index, 1);
                $('.eb-calendar-day[data-date="' + dateStr + '"]').removeClass('selected');
                this.updateSelectedDatesDisplay();
                this.updateBookButton();
            }
        },
        
        updateSelectedDatesDisplay: function() {
            var $container = $('#eb-selected-dates-list');
            $container.empty();
            
            if (this.selectedDates.length === 0) {
                $container.html('<p>まだ日付が選択されていません</p>');
                return;
            }
            
            var sortedDates = this.selectedDates.slice().sort();
            
            for (var i = 0; i < sortedDates.length; i++) {
                var date = new Date(sortedDates[i]);
                var dateText = this.formatDateDisplay(date);
                
                var $item = $('<div>')
                    .addClass('eb-selected-date-item')
                    .attr('data-date', sortedDates[i])
                    .html(
                        dateText +
                        '<span class="eb-selected-date-remove">&times;</span>'
                    );
                
                $container.append($item);
            }
        },
        
        updateBookButton: function() {
            var $button = $('#eb-book-button');
            if (this.selectedDates.length > 0) {
                $button.prop('disabled', false);
            } else {
                $button.prop('disabled', true);
            }
        },
        
        showBookingForm: function() {
            // Update form with selected dates
            var $formDates = $('#eb-form-selected-dates');
            $formDates.empty();
            
            var sortedDates = this.selectedDates.slice().sort();
            for (var i = 0; i < sortedDates.length; i++) {
                var date = new Date(sortedDates[i]);
                var dateText = this.formatDateDisplay(date);
                $formDates.append('<div class="eb-selected-date-item">' + dateText + '</div>');
            }
            
            // Clear form with safety check
            var $form = $('#eb-booking-form');
            if ($form.length > 0 && $form[0]) {
                $form[0].reset();
            }
            $('.eb-form-field').removeClass('has-error');
            $('.eb-error').remove();
            
            // Show/hide time slots section based on settings
            if (this.settings.enableTimeSlots && this.settings.timeSlots.length > 0) {
                $('.eb-form-section').has('.eb-time-slots').show();
            } else {
                $('.eb-form-section').has('.eb-time-slots').hide();
            }
            
            // Show modal
            $('#eb-booking-modal').show();
        },
        
        submitBooking: function() {
            var self = this;
            var $form = $('#eb-booking-form');
            var formData = {};
            
            // Collect form data
            $form.find('input, textarea, select').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                var value = $field.val();
                
                if ($field.attr('type') === 'checkbox') {
                    if (!formData[name]) {
                        formData[name] = [];
                    }
                    if ($field.is(':checked')) {
                        formData[name].push(value);
                    }
                } else if ($field.attr('type') === 'radio') {
                    if ($field.is(':checked')) {
                        formData[name] = value;
                    }
                } else {
                    formData[name] = value;
                }
            });
            
            // Show loading
            this.showLoading();
            
            // Submit via AJAX
            $.ajax({
                url: easyBookinger.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'eb_submit_booking',
                    nonce: easyBookinger.nonce,
                    booking_dates: this.selectedDates,
                    form_data: formData
                },
                success: function(response) {
                    self.hideLoading();
                    
                    if (response.success) {
                        self.showSuccessModal(response.data);
                        self.resetCalendar();
                    } else {
                        self.handleFormErrors(response.data);
                    }
                },
                error: function() {
                    self.hideLoading();
                    toastr.error(easyBookinger.text.bookingError);
                }
            });
        },
        
        showSuccessModal: function(data) {
            $('#eb-booking-modal').hide();
            
            var successHtml = '<h4>' + easyBookinger.text.bookingSuccess + '</h4>';
            successHtml += '<p>予約日: ' + data.booking_dates.join(', ') + '</p>';
            
            if (data.pdf_url) {
                successHtml += '<div class="eb-pdf-info">';
                successHtml += '<h5>📄 予約確認書（PDF）</h5>';
                successHtml += '<p>下記のリンクから予約確認書をダウンロードできます。</p>';
                successHtml += '<a href="' + data.pdf_url + '" class="eb-pdf-link" target="_blank">PDFをダウンロード</a>';
                successHtml += '<p><strong>パスワード:</strong> <span class="eb-password">' + data.pdf_password + '</span></p>';
                successHtml += '<p><small>※ パスワードは大切に保管してください。</small></p>';
                successHtml += '</div>';
            }
            
            $('#eb-success-content').html(successHtml);
            $('#eb-success-modal').show();
            
            toastr.success(easyBookinger.text.bookingSuccess);
        },
        
        handleFormErrors: function(data) {
            // Clear previous errors
            $('.eb-form-field').removeClass('has-error');
            $('.eb-error').remove();
            
            if (data.errors) {
                for (var field in data.errors) {
                    var $field = $('[name="' + field + '"]');
                    var $fieldContainer = $field.closest('.eb-form-field');
                    
                    $fieldContainer.addClass('has-error');
                    $fieldContainer.append('<div class="eb-error">' + data.errors[field] + '</div>');
                }
            }
            
            toastr.error(data.message || easyBookinger.text.validationError);
        },
        
        showLoading: function() {
            var loadingHtml = '<div class="eb-loading-overlay">';
            loadingHtml += '<div class="eb-loading">';
            loadingHtml += '<div class="eb-spinner"></div>';
            loadingHtml += '<p>' + easyBookinger.text.loading + '</p>';
            loadingHtml += '</div>';
            loadingHtml += '</div>';
            
            // Add loading overlay instead of replacing content
            var $modalContent = $('#eb-booking-modal .eb-modal-content');
            $modalContent.css('position', 'relative');
            $modalContent.append(loadingHtml);
        },
        
        hideLoading: function() {
            // Remove loading overlay
            $('.eb-loading-overlay').remove();
        },
        
        closeModal: function() {
            $('.eb-modal').hide();
            // Reset calendar after any modal closes to ensure clean state
            this.resetCalendar();
        },
        
        resetCalendar: function() {
            this.selectedDates = [];
            this.renderCalendar();
            this.updateBookedDates();
            this.updateBookButton(); // Ensure button state is reset
        },
        
        updateBookedDates: function() {
            var self = this;
            
            $.ajax({
                url: easyBookinger.ajaxUrl,
                type: 'GET',
                data: {
                    action: 'eb_get_calendar_data',
                    nonce: easyBookinger.nonce,
                    year: this.currentDate.getFullYear(),
                    month: this.currentDate.getMonth() + 1
                },
                success: function(response) {
                    if (response.success) {
                        self.bookedDates = response.data.booked_dates;
                        self.restrictedDates = response.data.restricted_dates || [];
                        self.quotasData = response.data.quotas_data || {};
                        self.renderCalendar();
                    }
                }
            });
        },
        
        formatDate: function(date) {
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            return year + '-' + month + '-' + day;
        },
        
        formatDateDisplay: function(date) {
            var year = date.getFullYear();
            var month = date.getMonth() + 1;
            var day = date.getDate();
            var dayNames = easyBookinger.dayNames;
            var dayOfWeek = dayNames[date.getDay()];
            
            return year + '年' + month + '月' + day + '日（' + dayOfWeek + '）';
        },
        
        isSameDate: function(date1, date2) {
            return date1.getFullYear() === date2.getFullYear() &&
                   date1.getMonth() === date2.getMonth() &&
                   date1.getDate() === date2.getDate();
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        // Toastr configuration
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": false,
            "onclick": null,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };
    });
    
})(jQuery);
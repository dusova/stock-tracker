/**
 * Cemil Çalışkan Stok Takip Sistemi
 * Ana JavaScript Dosyası
 */

$(document).ready(function() {
    // Sidebar Toggle
    $('#sidebarToggle').on('click', function() {
        $('#sidebar').toggleClass('collapsed');
        localStorage.setItem('sidebarCollapsed', $('#sidebar').hasClass('collapsed'));
    });

    // Sidebar durumunu localStorage'dan al
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        $('#sidebar').addClass('collapsed');
    }

    // Mobile Sidebar Toggle
    if ($(window).width() <= 992) {
        $('#sidebarToggle').on('click', function() {
            $('#sidebar').toggleClass('active');
        });
    }

    // DataTables Türkçe ayarları
    if ($.fn.DataTable) {
        $.extend(true, $.fn.dataTable.defaults, {
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/tr.json'
            },
            pageLength: 10,
            responsive: true
        });
    }

    // Select2 varsayılan ayarları
    if ($.fn.select2) {
        $.fn.select2.defaults.set('theme', 'bootstrap-5');
        $.fn.select2.defaults.set('language', 'tr');
    }

    // Form validation
    $('form').on('submit', function(e) {
        const requiredFields = $(this).find('[required]');
        let isValid = true;

        requiredFields.each(function() {
            if (!$(this).val()) {
                isValid = false;
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });

        if (!isValid) {
            e.preventDefault();
            showAlert('Lütfen zorunlu alanları doldurun.', 'warning');
        }
    });

    // Input change remove invalid class
    $('input, select, textarea').on('change input', function() {
        $(this).removeClass('is-invalid');
    });

    // Auto-calculate functions
    initCalculations();
});

// Para formatı
function formatMoney(amount) {
    return new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency: 'TRY',
        minimumFractionDigits: 2
    }).format(amount);
}

// Tarih formatı
function formatDate(date) {
    return new Intl.DateTimeFormat('tr-TR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).format(new Date(date));
}

// Alert göster
function showAlert(message, type = 'success') {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });

    Toast.fire({
        icon: type,
        title: message
    });
}

// Onay dialogu
function confirmAction(title, text, callback) {
    Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4361ee',
        cancelButtonColor: '#ef476f',
        confirmButtonText: 'Evet, devam et',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed && typeof callback === 'function') {
            callback();
        }
    });
}

// Silme onayı
function confirmDelete(callback) {
    confirmAction(
        'Silmek istediğinize emin misiniz?',
        'Bu işlem geri alınamaz!',
        callback
    );
}

// KDV hesaplama
function calculateVAT(amount, rate = 18) {
    const vatAmount = (amount * rate) / 100;
    return {
        base: amount,
        vatRate: rate,
        vatAmount: vatAmount,
        total: amount + vatAmount
    };
}

// İşlem formu hesaplamaları
function initCalculations() {
    const $quantity = $('#miktar');
    const $unitPrice = $('#birim_fiyat');
    const $vatRate = $('#kdv_orani');
    const $subtotal = $('#ara_toplam');
    const $vatAmount = $('#kdv_tutari');
    const $total = $('#genel_toplam');

    function updateCalculations() {
        const quantity = parseFloat($quantity.val()) || 0;
        const unitPrice = parseFloat($unitPrice.val()) || 0;
        const vatRate = parseFloat($vatRate.val()) || 18;

        const subtotal = quantity * unitPrice;
        const vatAmount = (subtotal * vatRate) / 100;
        const total = subtotal + vatAmount;

        $subtotal.text(formatMoney(subtotal));
        $vatAmount.text(formatMoney(vatAmount));
        $total.val(total.toFixed(2));
        $('#genel_toplam_display').text(formatMoney(total));
    }

    $quantity.on('input', updateCalculations);
    $unitPrice.on('input', updateCalculations);
    $vatRate.on('change', updateCalculations);
}

// AJAX request helper
function ajaxRequest(url, method, data, successCallback, errorCallback) {
    $.ajax({
        url: url,
        method: method,
        data: data,
        dataType: 'json',
        beforeSend: function() {
            // Loading göster
            $('body').append('<div class="spinner-overlay"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Yükleniyor...</span></div></div>');
        },
        success: function(response) {
            $('.spinner-overlay').remove();
            if (typeof successCallback === 'function') {
                successCallback(response);
            }
        },
        error: function(xhr, status, error) {
            $('.spinner-overlay').remove();
            console.error('AJAX Error:', error);
            if (typeof errorCallback === 'function') {
                errorCallback(xhr, status, error);
            } else {
                showAlert('Bir hata oluştu. Lütfen tekrar deneyin.', 'error');
            }
        }
    });
}

// Ürün silme
function deleteProduct(id) {
    confirmDelete(function() {
        ajaxRequest('api/products.php', 'DELETE', { id: id }, function(response) {
            if (response.success) {
                showAlert('Ürün başarıyla silindi.');
                location.reload();
            } else {
                showAlert(response.message || 'Silme işlemi başarısız.', 'error');
            }
        });
    });
}

// Müşteri silme
function deleteCustomer(id) {
    confirmDelete(function() {
        ajaxRequest('api/customers.php', 'DELETE', { id: id }, function(response) {
            if (response.success) {
                showAlert('Müşteri başarıyla silindi.');
                location.reload();
            } else {
                showAlert(response.message || 'Silme işlemi başarısız.', 'error');
            }
        });
    });
}

// İşlem silme
function deleteTransaction(id) {
    confirmDelete(function() {
        ajaxRequest('api/transactions.php', 'DELETE', { id: id }, function(response) {
            if (response.success) {
                showAlert('İşlem başarıyla silindi.');
                location.reload();
            } else {
                showAlert(response.message || 'Silme işlemi başarısız.', 'error');
            }
        });
    });
}

// Ürün seçildiğinde fiyatı getir
function onProductSelect(selectElement) {
    const selectedOption = $(selectElement).find(':selected');
    const satisFiyati = selectedOption.data('satis-fiyati');
    const alisFiyati = selectedOption.data('alis-fiyati');
    const islemTipi = $('#islem_tipi').val();

    if (islemTipi === 'satis') {
        $('#birim_fiyat').val(satisFiyati || 0);
    } else {
        $('#birim_fiyat').val(alisFiyati || 0);
    }

    // Hesaplamaları güncelle
    $('#birim_fiyat').trigger('input');
}

// İşlem tipi değiştiğinde fiyatı güncelle
function onTransactionTypeChange() {
    const selectedProduct = $('#urun_id').find(':selected');
    if (selectedProduct.val()) {
        onProductSelect('#urun_id');
    }
}

// Print function
function printPage() {
    window.print();
}

// Export to Excel (basic)
function exportToExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    const wb = XLSX.utils.table_to_book(table, { sheet: "Sheet1" });
    XLSX.writeFile(wb, filename + '.xlsx');
}

// Modal form temizle
function clearModalForm(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
            $(form).find('.is-invalid').removeClass('is-invalid');
            $(form).find('[type="hidden"]').not('[name="_token"]').val('');
        }
    }
}

// Düzenleme modalını aç
function openEditModal(modalId, data) {
    const modal = document.getElementById(modalId);
    if (modal && data) {
        Object.keys(data).forEach(key => {
            const input = modal.querySelector(`[name="${key}"]`);
            if (input) {
                if (input.type === 'checkbox') {
                    input.checked = data[key] == 1;
                } else {
                    input.value = data[key];
                }
            }
        });
        new bootstrap.Modal(modal).show();
    }
}

$(document).ready(function(){
    $('#editProductModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget); 
        var productId = button.data('product-id');
        var productName = button.data('product-name');
        var productDesc = button.data('product-desc');
        var productPrice = button.data('product-price');
        var productStock = button.data('product-stock');
        var productCategory = button.data('product-category');

        $('#editProductName').val(productName);
        $('#editProductId').val(productId);
        $('#editProductDesc').val(productDesc);
        $('#editProductPrice').val(productPrice);
        $('#editProductStock').val(productStock);
        $('#editProductCategory').val(productCategory);

    });
    $('.toggle-product').on('click', function() {
        var productId = $(this).data('product-id');
        var isDisabled = $(this).data('is-disabled');
        var action = isDisabled ? "enable" : "disable";

        $('#toggleAction').text(action);
        $('#toggleProductModal').modal('show');

        $('#confirmToggle').off('click').on('click', function() {
            $.ajax({
                type: "POST",
                url: "../PHP/toggle_product.php", 
                data: {
                    product_id: productId,
                    is_disabled: !isDisabled 
                },
                success: function(response) {
                    window.location.reload(true);
                },
                error: function() {
                    alert('Error toggling product status.');
                }
            });
        });
    });
    $('.btn-danger[data-bs-target="#deleteProductModal"]').on('click', function() {
        var productId = $(this).data('product-id');

        $('#confirmDelete').off('click').on('click', function() {
            $.ajax({
                type: "POST",
                url: "../PHP/delete_product.php",
                data: {
                    product_id: productId,
                },
                success: function(response) {
                    console.log(response)
                    window.location.reload(true);
                },
                error: function() {
                    alert('Error deleting product.');
                }
            });
        });
    });
});

$('#editProductForm').submit(function(event) {
    event.preventDefault();
    var formData = new FormData($("#editProductForm")[0]);
    formData.append('file', document.getElementById("formFileEdit").files[0]);
    console.log(formData)

    $.ajax({
        type: "POST",
        url: "../PHP/edit_product.php",
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            var alertHtml = '';
            if(response.status === 'success') {
                window.location.reload(true);
            } else if(response.message === 'NameExists') {
                alertHtml = '<div class="alert alert-warning" role="alert">Product Name Exists!</div>';
                return;
            } else {
                alertHtml = '<div class="alert alert-danger" role="alert">Failed to update product.</div>';
                return;
            }

            window.location.reload(true);
    
            $('#alertPlaceholder').html(alertHtml);
            setTimeout(function() {
                $('#alertPlaceholder').html('');
            }, 5000); // 5 seconds
        },
        error: function(xhr, status, error) {
            console.log("AJAX Error: " + status + " - " + error);
        }
    });
});

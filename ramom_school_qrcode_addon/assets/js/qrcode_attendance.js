function staffQuickView(id) {
    var inOutTime = $('input[name="in_out_time"]:checked').val();
    $.ajax({
        url: base_url + 'qrcode_attendance/getUserByQrcode',
        type: 'POST',
        data: {'data': id, 'in_out_time': inOutTime},
        dataType: 'json',
        success: function (res) {
        	if (res.status == 'successful') {
				if (confirmation_popup == 1) {
					$("#quick_image").attr("src", res.photo);
					$('#quick_email').html(res.email);
					console.log(res.userType);
					if (res.userType == 'staff') {
			            $('#quick_full_name').html(res.name);
			            $('#quick_role').html(res.role);
			            $('#quick_staff_id').html(res.staff_id);
			            $('#quick_joining_date').html(res.joining_date);
			            $('#quick_department').html(res.department);
			            $('#quick_designation').html(res.designation);
			            $('#quick_gender').html(res.gender);
			            $('#quick_blood_group').html(res.blood_group);
			            mfp_modal('#qr_staffDetails');
		        	}
					if (res.userType == 'student') {
			            $('#quick_full_name').html(res.full_name);
			            $('#quick_category').html(res.student_category);
			            $('#quick_register_no').html(res.register_no);
			            $('#quick_roll').html(res.roll);
			            $('#quick_admission_date').html(res.admission_date);
			            $('#quick_date_of_birth').html(res.birthday);
			            $('#quick_class_name').html(res.class_name);
			            $('#quick_section_name').html(res.section_name);
			            mfp_modal('#qr_studentDetails');
		        	}
				} else {
					attendance_submit();
				}
        	} else {
				swal({
					title: "Failed",
					html: res.message,
					type: "error",
					showCancelButton: false,
					confirmButtonClass: "btn btn-default swal2-btn-default",
					confirmButtonText: "OK",
					buttonsStyling: false,
					timer: 6000
				}).then((result) => {
					$("#qr_status").html(statusScanning);
					modalOpen = 0;
				});
        	}
        }
    });
}

function end_loader() {
	setTimeout(function (){
		$("#qr_status").html(statusScanning);
		lastResult = 0;
		modalOpen = 0;
	}, 1000);
}
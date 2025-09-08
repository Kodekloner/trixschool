$(document).ready(function(){
    var table = $('#example').DataTable({
        scrollX: true,
        buttons:['copy', 'csv', 'excel', 'pdf', 'print']
    });

    table.buttons().container()
    .appendTo('#example_wrapper .col-md-6:eq(0)');
});


///this is Grading list page
$("body").on('change','#gradeNumber',function(){
    // alert('wrking');
    $('#newgradeline').html('');
    var amt = $(this).val();
    for (let i = 0; i < amt; i++) {
        $('#newgradeline').append('<div class="form-row"> <div class="col-sm-3"><input type="text" class="form-control form-control-sm" placeholder="Grade"></div><div class="col-sm-3"><input type="number" class="form-control form-control-sm" placeholder="from"></div><div class="col-sm-3"><input type="number" class="form-control form-control-sm" placeholder="Upto"></div><div class="col-sm-3"><input type="text" class="form-control form-control-sm" placeholder="remark"></div></div><br>');
    }
});



  ///this is Grading System page
$("body").on('change','#caNumber',function(){
    // alert('wrking');
    $('#createCAForm').html('');
    var amt = $(this).val();
    for (let i = 0; i < amt; i++) {
        $('#createCAForm').append('<div class="form-row"> <div class="col-sm-4"> <input type="text" class="form-control form-control-sm" placeholder="CA Name"> </div> <div class="col-sm-3"> <input type="number" class="form-control form-control-sm" placeholder="Score"> </div> <div class="col-sm-5"> <div class="form-check form-check-inline"> <label class="form-check-label" for="defaultCheck1" style="font-size: 13.5px; font-weight: 700; text-align: center;"> Use For Mid-Term </label> <input class="form-check-input" id="defaultCheck1" type="checkbox" value="" style="margin-left: 4px; margin-top: 4px;"> </div> </div> </div> <br>');
    }
});


///this is Exam list page
$("body").on('change','#subjectNumber',function(){
    // alert('wrking');
    $('#addExamNumber').html('');
    var amt = $(this).val();
    for (let i = 0; i < amt; i++) {
        $('#addExamNumber').append('<div class="form-row"> <div class="col-sm-3"> <select class="form-control form-control-sm"> <option>Subjects</option> <option>Mathematics</option> <option>English</option> <option>Verbal Reasoning</option> <option>Social Studies</option> <option>Computer Studies</option> <option>Homes Economics</option> <option>Civil Education</option> <option>Basic Science</option> <option>C.R.S</option> </select> </div> <div class="col-sm-3"> <input type="date" class="form-control form-control-sm" placeholder="Exam Date"> </div> <div class="col-sm-3"> <input type="time" class="form-control form-control-sm" placeholder="Exam Time"> </div> <div class="col-sm-3"> <input type="text" class="form-control form-control-sm" placeholder="Duration"> </div> </div> <br>');
    }
});



function selects(){  
    var ele=document.getElementsByName('chkBoc');  
    for(var i=0; i<ele.length; i++){  
        if(ele[i].type=='checkbox')  
            ele[i].checked=true;  
    }  
}  
function deSelect(){  
    var ele=document.getElementsByName('chkBoc');  
    for(var i=0; i<ele.length; i++){  
        if(ele[i].type=='checkbox')  
            ele[i].checked=false;  
          
    }  
}            
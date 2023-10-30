function initAnySummerNote() {

    // find any select2 element with class .select2
    $('.summernote').each(function (index, object) {

        const elementId = $(this).attr('id');
        const inputEntryId = "" + elementId + "-Input";
        $(this).summernote({
            tabsize: 2,
            height: 320,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link', 'picture', 'video']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ],
        });

        $(this).on('summernote.change', function (we, contents, $editable) {
            $("#" + inputEntryId).val(contents);
            document.getElementById(inputEntryId).dispatchEvent(new Event('input'));
        });


    });


    //
    Livewire.on('loadSummerNote', function (elementId, text) {

        if (text != null) {
            $("#" + elementId).summernote('code', text);
            const inputEntryId = "" + elementId + "-Input";
            $("#" + elementId).on('summernote.change', function (we, contents, $editable) {
                $("#" + inputEntryId).val(contents);
                document.getElementById(inputEntryId).dispatchEvent(new Event('input'));
            });
        }
    });

}



// jqery on page load
$(function () {

    initAnySummerNote();

});

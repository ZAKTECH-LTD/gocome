$(function () {

    var toolbarOptions = [
        ['bold', 'italic', 'underline', 'strike'],        // toggled buttons
        ['blockquote', 'code-block'],

        [{ 'header': 1 }, { 'header': 2 }],               // custom button values
        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
        [{ 'script': 'sub' }, { 'script': 'super' }],      // superscript/subscript
        [{ 'indent': '-1' }, { 'indent': '+1' }],          // outdent/indent
        [{ 'direction': 'rtl' }],                         // text direction

        [{ 'size': ['small', false, 'large', 'huge'] }],  // custom dropdown
        [{ 'header': [1, 2, 3, 4, 5, 6, false] }],

        [{ 'color': [] }, { 'background': [] }],          // dropdown with defaults from theme
        [{ 'font': [] }],
        [{ 'align': [] }],

        ['clean']                                         // remove formatting button
    ];


    var newEditor = new Quill("#newDescription", {
        theme: 'snow',
        modules: {
            toolbar: toolbarOptions
        }
    });
    var editEditor = new Quill("#editDescription", {
        theme: 'snow', modules: {
            toolbar: toolbarOptions
        }
    });


    //text change listeners
    newEditor.on('text-change', function () {
        const contents = newEditor.root.innerHTML;
        document.getElementById("description").value = contents;
        document.getElementById("description").dispatchEvent(new Event('input'));
    });


    editEditor.on('text-change', function () {
        const contents = editEditor.root.innerHTML;
        document.getElementById("description").value = contents;
        document.getElementById("description").dispatchEvent(new Event('input'));
    });



    livewire.on("prepCustomWYSISYG", data => {

        const selectorData = data[0];
        if (selectorData != null) {
            editEditor.root.innerHTML = selectorData;
            newEditor.root.innerHTML = selectorData;
        }

    });

});
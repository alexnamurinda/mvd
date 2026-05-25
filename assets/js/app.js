// BusinessPro - light client-side JS

document.addEventListener('DOMContentLoaded', function() {

    // Auto-dismiss flash alerts after 4s
    document.querySelectorAll('.alert.auto-dismiss').forEach(function(el){
        setTimeout(function(){
            el.style.transition = 'opacity .35s';
            el.style.opacity = '0';
            setTimeout(function(){ el.remove(); }, 400);
        }, 4000);
    });

    // Confirm delete links
    document.querySelectorAll('[data-confirm]').forEach(function(el){
        el.addEventListener('click', function(e){
            if (!confirm(el.getAttribute('data-confirm') || 'Are you sure?')) {
                e.preventDefault();
            }
        });
    });

    // Search filter (client-side simple)
    document.querySelectorAll('[data-search-target]').forEach(function(input){
        var target = document.querySelector(input.getAttribute('data-search-target'));
        if (!target) return;
        input.addEventListener('input', function(){
            var q = input.value.toLowerCase().trim();
            target.querySelectorAll('[data-search-row]').forEach(function(row){
                var text = (row.getAttribute('data-search-row') || '').toLowerCase();
                row.style.display = (q === '' || text.indexOf(q) !== -1) ? '' : 'none';
            });
        });
    });

    // Numeric amount auto-format on blur (separators)
    document.querySelectorAll('[data-money]').forEach(function(inp){
        inp.addEventListener('blur', function(){
            var v = parseFloat(inp.value);
            if (!isNaN(v)) inp.value = v.toFixed(0);
        });
    });

});

// Print helper
function printDoc() {
    window.print();
}

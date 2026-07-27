 /**
  * MyApp - 前端脚本
  */
 
 document.addEventListener('DOMContentLoaded', function() {
     
     // 自动隐藏 Flash 消息
     const alerts = document.querySelectorAll('.alert');
     alerts.forEach(function(alert) {
         setTimeout(function() {
             alert.style.transition = 'opacity 0.5s';
             alert.style.opacity = '0';
             setTimeout(function() { alert.remove(); }, 500);
         }, 5000);
     });
     
     // 确认对话框
     document.querySelectorAll('[data-confirm]').forEach(function(el) {
         el.addEventListener('click', function(e) {
             if (!confirm(el.dataset.confirm)) {
                 e.preventDefault();
             }
         });
     });
     
     console.log('MyApp loaded successfully');
 });

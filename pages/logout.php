<?php
 /**
  * 退出登录
  */
 logout();
 setFlash('success', '已安全退出');
 redirect('index.php?route=home');

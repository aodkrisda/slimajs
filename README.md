#slimajs
Basic SlimPHP &amp; AngularJS RESTfull cms
Login as:
admin@admin.pl / admin

demo: (soon)


##CONFIG:
This MySql version, just run this sql and you ready to go:
```sql
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=3 ;

INSERT INTO `users` (`id`, `email`, `password`, `role`) VALUES
(2, 'admin@admin.pl', '21232f297a57a5a743894a0e4a801fc3', 'user');
```


##About safety
* RedBean ORM so dont worry about sql injection
* CsrfGuard Module included - any POST / PUT / DELETE request must have a valid token


##Todo
A lot :)


Czemu ja to piszę po EN .. ?

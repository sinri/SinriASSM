# SinriASSM
Sinri Apache Server Status Monitor

## Fact

We use apache to host web site to provide service, and also some cronjob scripts run on the same server. Recently more and more requests comes and server failed serveral time, however I cannot know for what it died. At least I should know which process blocked the common jobs.

Command `ps` or `top` could display CPU and time, but it could not show details for apache processes. We have to read the PID from here, and search it in Apache Server Status page for detail request, to find out which file we need to optimize.

Well, I wrote a script to do this automatically, including filtering processes and joining Apache Server Status information.


## Principle

Give a percent constant and filter processes with `ps aux`, any processes using CPU over this would be left, and then check Apache (Version 2.2) Server Status page, join the very line to the process with PID.

## Sample Output

	ljni@hz97-164-23:~$ php SinriASSM.php 
	Check any processes using beyond 0% of Full CPU...
	Current Process Count: 44
	Filtered to 10
	#PID: 1150
	www-data  1150  0.3  0.1 265204 23444 ?        S    20:53   0:19 /usr/sbin/apache2 -k start
	7-1	1150	0/639/183327	_	19.68	21	1	0.0	1.88	755.08	192.168.2.5	ecadmin.leqee.com	GET	/admin/misc/thickbox.js	HTTP/1.0
	#PID: 3853
	nobody    3853  0.1  0.0  18596  1576 ?        DN   06:25   0:59 /usr/bin/find / -ignore_readdir_race ( -fstype NFS -o -fstype nfs -o -fstype nfs4 -o -fstype afs -o -fstype binfmt_misc -o -fstype proc -o -fstype smbfs -o -fstype autofs -o -fstype iso9660 -o -fstype ncpfs -o -fstype coda -o -fstype devpts -o -fstype ftpfs -o -fstype devfs -o -fstype mfs -o -fstype shfs -o -fstype sysfs -o -fstype cifs -o -fstype lustre_lite -o -fstype tmpfs -o -fstype usbfs -o -fstype udf -o -fstype ocfs2 -o -type d -regex \(^/tmp$\)\|\(^/usr/tmp$\)\|\(^/var/tmp$\)\|\(^/afs$\)\|\(^/amd$\)\|\(^/alex$\)\|\(^/var/spool$\)\|\(^/sfs$\)\|\(^/media$\)\|\(^/var/lib/schroot/mount$\) ) -prune -o -print0

	#PID: 4701
	www-data  4701  0.7  0.1 265984 25212 ?        S    21:12   0:35 /usr/sbin/apache2 -k start
	3-1	4701	0/518/201767	_	35.27	13	31	0.0	1.58	834.13	192.168.2.5	ecadmin.leqee.com	POST	/admin/refund_list_new.php?request=ajax	HTTP/1.0
	#PID: 4702
	www-data  4702  0.3  0.1 263348 21760 ?        S    21:12   0:16 /usr/sbin/apache2 -k start
	9-1	4702	0/525/174010	_	16.12	21	166	0.0	1.29	750.48	192.168.2.5	ecadmin.leqee.com	GET	/admin/orderV2/sales_order_edit_ajax.php?order_id=14868138&amp;	
	#PID: 5942
	www-data  5942  0.5  0.1 269568 27252 ?        S    18:21   1:15 /usr/sbin/apache2 -k start
	8-1	5942	0/1974/181610	W	77.82	0	0	0.0	8.07	726.81	192.168.2.5	ecadmin.leqee.com	GET	/server-status	HTTP/1.0
	#PID: 6470
	www-data  6470  0.5  0.1 268456 24836 ?        S    18:24   1:24 /usr/sbin/apache2 -k start
	5-1	6470	0/2113/195297	_	84.99	21	1	0.0	8.76	788.03	192.168.2.5	ecadmin.leqee.com	GET	/admin/misc/thickbox.css	HTTP/1.0
	#PID: 8217
	www-data  8217  0.3  0.1 259376 18276 ?        S    21:31   0:11 /usr/sbin/apache2 -k start
	0-1	8217	0/399/207486	_	11.42	21	1	0.0	0.94	873.15	192.168.2.5	ecadmin.leqee.com	GET	/admin/sale_support/images/image/bg_header.jpg	HTTP/1.0
	#PID: 8218
	www-data  8218  0.3  0.1 267280 26032 ?        S    21:31   0:10 /usr/sbin/apache2 -k start
	2-1	8218	0/388/198819	_	10.75	21	4	0.0	1.65	788.28	192.168.2.5	ecadmin.leqee.com	GET	/admin/misc/jquery.js	HTTP/1.0
	#PID: 20443
	www-data 20443  0.5  0.1 265960 25376 ?        S    19:41   0:59 /usr/sbin/apache2 -k start
	6-1	20443	0/1195/196828	_	60.29	21	1	0.0	4.24	794.00	192.168.2.5	ecadmin.leqee.com	GET	/admin/styles/css/css_2007.9.8.css	HTTP/1.0
	#PID: 23539
	www-data 23539  0.3  0.1 263640 22800 ?        S    19:59   0:34 /usr/sbin/apache2 -k start
	4-1	23539	0/1031/204411	_	33.63	20	73	0.0	4.22	832.51	192.168.2.5	ecadmin.leqee.com	GET	/admin/sale_support/sale_support.php?order_id=14868138	HTTP
	#PID: 29145
	www-data 29145  0.3  0.1 265496 24128 ?        S    20:29   0:26 /usr/sbin/apache2 -k start
	10-1	29145	0/792/152525	_	26.35	21	1	0.0	2.30	622.14	192.168.2.5	ecadmin.leqee.com	GET	/admin/styles/css/css.css	HTTP/1.0
	ljni@hz97-164-23:~$ 

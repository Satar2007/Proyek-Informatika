\# Week 1 Evidence - Attendance, Shift, and Leave Management



\## Identitas Kontributor



Nama Kontributor:

Afrino



Modul:

Attendance, Shift, dan Leave Management



Repository:

Satar2007/Proyek-Informatika



Branch:

week1-attendance-shift-leave-audit





\## Baseline



Baseline branch:

main



Baseline commit:

Mengikuti main terbaru sebelum kontribusi Week 1





\## Tanggung Jawab Modul



Kontribusi berfokus pada audit dan validasi modul:



\- Attendance Management

\- Shift Management

\- Leave Request Management





\## Audit Implementasi



Audit dilakukan terhadap:



\- AttendanceController

\- ShiftController

\- LeaveRequestController

\- Model terkait

\- Routes

\- Feature Tests





\## Hasil Audit



\### Attendance



Pengujian dilakukan terhadap:



\- Clock-in normal

\- Keterlambatan clock-in

\- Batas toleransi keterlambatan

\- Pencegahan clock-in ganda

\- Integritas attendance terhadap shift





Hasil:

Tidak ditemukan defect fungsional.





\### Shift



Pengujian dilakukan terhadap:



\- Generate shift seluruh tanggal dalam rentang

\- Validasi rentang tanggal

\- Pencegahan duplikasi shift

\- Penghapusan shift dengan histori attendance

\- Penghapusan shift dengan pending leave





Hasil:

Tidak ditemukan defect fungsional.





\### Leave Management



Ditemukan kebutuhan peningkatan coverage pengujian pada flow leave request.



Ditambahkan regression test:



\- Kasir dapat mengajukan izin dengan shift valid

\- Kasir tidak dapat mengajukan izin tanpa shift

\- Kasir tidak dapat mengajukan izin setelah clock-in

\- Admin dapat menyetujui izin pending

\- Admin dapat menolak izin pending





\## Testing Evidence



\### Leave Request Test



Command:



php artisan test --filter=LeaveRequestIntegrityTest



Result:



5 passed

12 assertions





\### Modul Regression Test



Command:



php artisan test --filter="Attendance|Shift|Leave"



Result:



11 passed

39 assertions





\### Full Laravel Regression



Command:



php artisan test



Result:



83 passed

330 assertions





\## Perubahan yang Dilakukan



Tidak ada perubahan pada:



\- Controller

\- Model

\- Migration

\- Route



Perubahan hanya berupa penambahan regression test:



tests/Feature/LeaveRequestIntegrityTest.php





\## Commit Evidence



Commit:



45d1ffe



Message:



Add leave request integrity tests for week 1 audit





\## Kesimpulan



Audit Week 1 pada modul Attendance, Shift, dan Leave berhasil dilakukan.



Hasil pengujian menunjukkan seluruh fitur berjalan sesuai requirement. Tidak ditemukan defect fungsional. Penambahan regression test dilakukan untuk meningkatkan coverage pengujian pada modul Leave Management.



Branch kontribusi siap untuk proses Pull Request menuju main.


BEGIN TRANSACTION;
CREATE TABLE anggota (
  id TEXT PRIMARY KEY,
  nama TEXT,
  nip TEXT,
  jabatan TEXT,
  unit TEXT,
  whatsapp TEXT,
  email TEXT,
  kompetensi TEXT,
  spesialisasi TEXT,
  sertifikasi TEXT,
  status TEXT CHECK (status IS NULL OR status IN ('Aktif','Nonaktif')),
  keterangan TEXT,
  extra_json TEXT
);
INSERT INTO "anggota" VALUES('AG-001','DESIANA KARTIKA BAHARI S.E, M.H.','197812092010122001','Kepala Kantor','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pembina Tk. I (IV/b); TMT Golongan: 01/04/2026; TMT Jabatan: 10/01/2024',NULL);
INSERT INTO "anggota" VALUES('AG-002','ABDULLAH KOMAENY S.H., M.H.','198305222009121001','Kepala Subbagian Umum','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pembina (IV/a); TMT Golongan: 01/01/2026; TMT Jabatan: 13/11/2025',NULL);
INSERT INTO "anggota" VALUES('AG-003','AKHMAD RIZKIANSAH S.Kom.,M.Sc.','198003012010121001','Kepala Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Tk. I (III/d); TMT Golongan: 01/04/2024; TMT Jabatan: 15/01/2025',NULL);
INSERT INTO "anggota" VALUES('AG-004','DIDIT PERMANA S.Kom.','197708162009121003','Kepala Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Tk. I (III/d); TMT Golongan: 01/10/2022; TMT Jabatan: 15/01/2025',NULL);
INSERT INTO "anggota" VALUES('AG-005','ENGKOS','198204102025211066','Operator Layanan Operasional','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','TMT Jabatan: 01/10/2025',NULL);
INSERT INTO "anggota" VALUES('AG-006','SIGIT DJOKO PRASETYO','198101262025211029','Operator Layanan Operasional','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','TMT Jabatan: 01/10/2025',NULL);
INSERT INTO "anggota" VALUES('AG-007','ARIF TIRTA PRAWITA','199309182025211070','Operator Layanan Operasional','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','TMT Jabatan: 01/10/2025',NULL);
INSERT INTO "anggota" VALUES('AG-008','SAIMIMAN','199808082025211041','Operator Layanan Operasional','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','TMT Jabatan: 01/10/2025',NULL);
INSERT INTO "anggota" VALUES('AG-009','NICO ALFACHREZA DARMAWAN','200105242025211010','Operator Layanan Operasional','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','TMT Jabatan: 01/10/2025',NULL);
INSERT INTO "anggota" VALUES('AG-010','FATHUR WARDHANI','199905152025211023','Operator Layanan Operasional','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','TMT Jabatan: 01/10/2025',NULL);
INSERT INTO "anggota" VALUES('AG-011','GANJAR SUWARDI H','199409152025211049','Operator Layanan Operasional','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','TMT Jabatan: 01/10/2025',NULL);
INSERT INTO "anggota" VALUES('AG-012','TAUFIK HIDAYAT','200004132025211024','Operator Layanan Operasional','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','TMT Jabatan: 01/10/2025',NULL);
INSERT INTO "anggota" VALUES('AG-013','ESA ASEP SAEPUDIN','199412122025211012','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025',NULL);
INSERT INTO "anggota" VALUES('AG-014','FONDA SURYO TOSAN AJI','199010072025211032','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025',NULL);
INSERT INTO "anggota" VALUES('AG-015','RIANTO PRIMADANI','199704032025211005','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025',NULL);
INSERT INTO "anggota" VALUES('AG-016','WILLYAMSYAH IRTANTA','199608192025211003','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025',NULL);
INSERT INTO "anggota" VALUES('AG-017','ARIS USDOKO','197806232025211007','Operator Layanan Operasional Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025',NULL);
INSERT INTO "anggota" VALUES('AG-018','DEDE IRAWAN','198107102025211005','Operator Layanan Operasional Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025',NULL);
INSERT INTO "anggota" VALUES('AG-019','HAERUL UMAM','199504232025211009','Operator Layanan Operasional Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025',NULL);
INSERT INTO "anggota" VALUES('AG-020','SEHAT AGUSTINUS RIZALDI ROY SITEPU','198308142025211024','Operator Layanan Operasional Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025',NULL);
INSERT INTO "anggota" VALUES('AG-021','SYUKRIA NOFRIZAL','199211212025211009','Operator Layanan Operasional Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025',NULL);
INSERT INTO "anggota" VALUES('AG-022','WISHNU YUDHANTO','200111262025211002','Operator Layanan Operasional Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025',NULL);
INSERT INTO "anggota" VALUES('AG-023','ARIP RACHMAN HAKIM','199208212025211014','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: V; TMT Golongan: 01/03/2025; TMT Jabatan: 01/03/2025',NULL);
INSERT INTO "anggota" VALUES('AG-024','BAMBANG KUSWITO','199701162025211026','Operator Layanan Operasional Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: V; TMT Golongan: 01/10/2025; TMT Jabatan: 01/10/2025',NULL);
INSERT INTO "anggota" VALUES('AG-025','ROMY BATARA, S.E.','197801072005041002','Analis Pengelolaan Keuangan APBN Ahli Muda','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Tk. I (III/d); TMT Golongan: 01/10/2018; TMT Jabatan: 01/04/2021',NULL);
INSERT INTO "anggota" VALUES('AG-026','DJENAL ARIFIN, S.E.','197508272009121001','Penata Kelola Pencarian dan Pertolongan Ahli Muda','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Tk. I (III/d); TMT Golongan: 01/04/2022; TMT Jabatan: 27/06/2023',NULL);
INSERT INTO "anggota" VALUES('AG-027','DEVY HIDAYANTI S.E., M.A.','198712162010122005','Penelaah Teknis Kebijakan Subbagian Umum','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Tk. I (III/d); TMT Golongan: 01/04/2023; TMT Jabatan: 01/11/2025',NULL);
INSERT INTO "anggota" VALUES('AG-028','EDWARD RAMADHANA, S.Kom.','198207092005021001','Pengelola Pencarian dan Pertolongan Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata (III/c); TMT Golongan: 01/04/2023; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-029','ASEP SUPRIYATNA, S.E.','198411072005021001','Analis Pengelolaan Keuangan APBN Ahli Muda','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata (III/c); TMT Golongan: 01/04/2023; TMT Jabatan: 15/01/2025',NULL);
INSERT INTO "anggota" VALUES('AG-030','TEUKU SUFRIADI RAMADHANI S.T., M.Si.','197410012007121001','Markonis Kapal Kelas II Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata (III/c); TMT Golongan: 01/04/2024; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-031','MOHAMMAD IHWAN S.E.','197405302007121001','Mualim I Kapal Kelas II Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata (III/c); TMT Golongan: 01/04/2024; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-032','ENDRA RADENTA BARUS A.Md.','197409272008121001','Mualim I Kapal Kelas I Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata (III/c); TMT Golongan: 01/04/2025; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-033','HARDIANSYAH PUTRA S.E.','198512152010121003','Pranata Pencarian dan Pertolongan Penyelia Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata (III/c); TMT Golongan: 01/04/2026; TMT Jabatan: 17/12/2025',NULL);
INSERT INTO "anggota" VALUES('AG-034','NURUL KARLINA S.Sos.','198605052006042001','Pranata Hubungan Masyakarat Ahli Pertama Subbagian Umum','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/10/2017; TMT Jabatan: 01/12/2024',NULL);
INSERT INTO "anggota" VALUES('AG-035','MUHAMMAD ARIEF BUDIMAN','197206011999031002','Operator Layanan Operasional Subbagian Umum','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/10/2020; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-036','DITO PEBYAN PRASETYO A.MK','198701232007121002','Perawat Mahir Subbagian Umum','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/04/2022; TMT Jabatan: 01/03/2019',NULL);
INSERT INTO "anggota" VALUES('AG-037','DIAN RAHMANTO, A.Md.','198012022007121001','Pengelola Layanan Operasional Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/10/2022; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-038','TETTY ROSMAULI S.E.','198704142007122001','Penata Kelola Pencarian dan Pertolongan Ahli Pertama Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/04/2023; TMT Jabatan: 27/06/2023',NULL);
INSERT INTO "anggota" VALUES('AG-039','STEFANUS HARRENDRA OUCTHO FRENGKY S.AP.','198810282010121002','Pranata Pencarian dan Pertolongan Penyelia Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/04/2024; TMT Jabatan: 09/09/2026',NULL);
INSERT INTO "anggota" VALUES('AG-040','IRINNE IZDIHAR S.I.Kom.','199503122019022002','Pranata Humas Ahli Pertama Subbagian Umum','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/04/2024; TMT Jabatan: 01/01/2021',NULL);
INSERT INTO "anggota" VALUES('AG-041','ERDI JATMIKO S.M.','198802262007121001','Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/10/2024; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-042','PASUNDANU KRESNA S.Tr.Pel.','199609242020121003','Nakhoda Kapal Kelas I Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/12/2024; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-043','IMAM HANAGIA S.M.','198209132005021002','Mualim II Kapal Kelas II Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/04/2025; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-044','APRIANTO PRAPTOMO S.E.','198604132007121001','Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/10/2025; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-045','DARMAWAN','198302082007121002','Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/06/2026; TMT Jabatan: 10/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-046','CAHYADI ARI KUSUMO S.M.','198805212009121003','Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda Tk. I (III/b); TMT Golongan: 01/09/2026; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-047','IWAN SUBEKTI S.E.','197203252006041005','Mualim III Kelas I Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda (III/a); TMT Golongan: 01/10/2022; TMT Jabatan: 01/05/2026',NULL);
INSERT INTO "anggota" VALUES('AG-048','M. IRVAN KRISTIANTO A.Md.Pjk.','198605192010121002','Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda (III/a); TMT Golongan: 01/02/2024; TMT Jabatan: 25/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-049','FABER SINAGA','198804062008121001','Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda (III/a); TMT Golongan: 01/02/2024; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-050','ANDRIAS RESTIYANTO','198805122007121001','Nakhkoda Kapal Kelas IV Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda (III/a); TMT Golongan: 01/04/2024; TMT Jabatan: 01/05/2026',NULL);
INSERT INTO "anggota" VALUES('AG-051','DWI NUR ARIANI','198811292007122001','Pengelola Pencarian dan Pertolongan Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda (III/a); TMT Golongan: 01/04/2024; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-052','MAYSENO ADI NUGROHO','198205152007121002','Pengelola Layanan Operasional Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda (III/a); TMT Golongan: 01/04/2024; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-053','AZIZ KURNIAWAN','198009222007121002','Mandor Mesin Kapal Kelas II Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda (III/a); TMT Golongan: 01/04/2024; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-054','HOIRIYAH S.E.','198908162009122001','Arsiparis Mahir Subbagian Umum','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda (III/a); TMT Golongan: 01/02/2025; TMT Jabatan: 21/11/2024',NULL);
INSERT INTO "anggota" VALUES('AG-055','YUNITA SARI A.Md.','199106272010122002','Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda (III/a); TMT Golongan: 01/01/2026; TMT Jabatan: 13/11/2025',NULL);
INSERT INTO "anggota" VALUES('AG-056','VIDI OCTAVNUARI','199101082009121001','Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda (III/a); TMT Golongan: 01/03/2026; TMT Jabatan: 17/12/2025',NULL);
INSERT INTO "anggota" VALUES('AG-057','SURYO ADIANTO','199103162014021001','Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda (III/a); TMT Golongan: 01/09/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-058','AHMAD NUR IMAN','199010122010121001','Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda (III/a); TMT Golongan: 01/09/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-059','ZAINUDIN','198203282009121002','Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Penata Muda (III/a); TMT Golongan: 01/09/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-060','RAMLI PRASETIO, A.Md.','198410252007121003','Pranata Hubungan Masyarakat Terampil Subbagian Umum','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/04/2012; TMT Jabatan: 01/01/2018',NULL);
INSERT INTO "anggota" VALUES('AG-061','SAHAT PARULIAN','198903082009121002','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/04/2021; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-062','FAJAR LAKSANA','199205022010121002','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/10/2021; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-063','MUHAMMAD FAZRY M. ASLAM','199201262010121001','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/10/2021; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-064','ENDAH WATI A.Md.Pjk.','198808082010122003','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/04/2022; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-065','ZULFIKRI BINIS','199003242009121001','Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/04/2022; TMT Jabatan: 09/09/2026',NULL);
INSERT INTO "anggota" VALUES('AG-066','BOBY YOENARTHA PUTRA','198806082009121003','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/04/2022; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-067','DEVI DWI HARINI A.Md.','198809212010122001','Pengolah Data dan Informasi Subbagian Umum','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/10/2022; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-068','DIAN HERDANINGSIH','199106062010122001','Operator Layanan Operasional Subbagian Umum','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/10/2022; TMT Jabatan: 01/08/2026',NULL);
INSERT INTO "anggota" VALUES('AG-069','MUHAMMAD GUFRAN','199205052010121001','Juru Minyak Kapal Kelas II Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/04/2023; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-070','AHMAD KOSASIH','198306132010121002','Juru Minyak Kapal Kelas II Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/04/2023; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-071','I PUTU SUWARTIKA','198901182010121001','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/04/2023; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-072','WAHYU SUWIGNYO A.Md.Kom.','198912292014021002','Pranata Pencarian dan Pertolongan Mahir Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/04/2023; TMT Jabatan: 09/09/2026',NULL);
INSERT INTO "anggota" VALUES('AG-073','RIO SARDITYO UTOMO','198911252009121002','Pranata Keuangan APBN Terampil Subbagian Umum','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/10/2023; TMT Jabatan: 01/04/2021',NULL);
INSERT INTO "anggota" VALUES('AG-074','CANDRA AGUS SETIYAWAN A.Md.','199408232019021001','Nakhoda Kapal Kelas II Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/10/2023; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-075','DONNY PRATAMA','199203292010121002','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/02/2024; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-076','RAINALDO SITEPU A.Md.','199704132020121001','Pengelola Pencarian dan Pertolongan Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/12/2024; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-077','ALI MASHAR A.Md.','199011032020121002','Kepala Kamar Mesin Kapal Kelas II Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/12/2024; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-078','MEGAWATI','199207262010122001','Operator Layanan Operasional Subbagian Umum','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/10/2025; TMT Jabatan: 01/08/2026',NULL);
INSERT INTO "anggota" VALUES('AG-079','GUSTI WISNU WARDHANA PUTRA','199010232010121001','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/03/2026; TMT Jabatan: 01/07/2025',NULL);
INSERT INTO "anggota" VALUES('AG-080','AULIA SHOLIHANTO','199003022009121002','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/03/2026; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-081','MUHAMMAD RIZALDY FITRAH A.Md.','199702162017121004','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/09/2026; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-082','PAMBUDI NURWIDJAYA','199407272017121007','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/09/2026; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-083','SURYA ANGGA TIRTA SRIDARMAWAN A.Md.Pjk.','199807112017121009','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/09/2026; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-084','CIGRA APTA LABAN PONJOT','199404102017121003','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/09/2026; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-085','FANDU FAISAL','199511082017121006','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Tk. I (II/d); TMT Golongan: 01/09/2026; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-086','DHINAR CAHYO PUTRA','199707182017121005','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur (II/c); TMT Golongan: 01/02/2024; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-087','LINTANG AFI YUDHISTIRA','199501052017121006','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur (II/c); TMT Golongan: 01/02/2024; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-088','RIFAN GUSRIANTO','199304042017121009','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur (II/c); TMT Golongan: 01/02/2024; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-089','PRAYOGA NURBIYANTO','199803032017121005','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur (II/c); TMT Golongan: 01/02/2024; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-090','RESTI NINGSIH','199402202014022001','Operator Layanan Operasional','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur (II/c); TMT Golongan: 01/02/2025; TMT Jabatan: 01/08/2026',NULL);
INSERT INTO "anggota" VALUES('AG-091','MC RYAN CHRISTIAN SENDUK','199108302009121001','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur (II/c); TMT Golongan: 01/09/2025; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-092','MUHAMMAD ALVINDRA HUSSEN','199606252017121007','Juru Minyak Kapal Kelas I Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur (II/c); TMT Golongan: 01/04/2026; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-093','MUNARWI ATFAN','198606232017121002','Kepala Kamar Mesin Kapal Kelas I Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur (II/c); TMT Golongan: 01/04/2026; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-094','SIGIT PRASETYO','199702202017121005','Markonis Kapal Kelas I Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur (II/c); TMT Golongan: 01/04/2026; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-095','SUMARNA','199401212017121003','Juru Minyak Kapal Kelas I Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur (II/c); TMT Golongan: 01/04/2026; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-096','IRFAN KELIREY','199403272017121007','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur (II/c); TMT Golongan: 01/06/2026; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-097','MUHAMAD TAJUL ARIFIN A.Md.Kep.','200010082025061004','Pengelola Layanan Kesehatan Subbagian Umum','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur (II/c); TMT Golongan: 01/06/2026; TMT Jabatan: 01/06/2025',NULL);
INSERT INTO "anggota" VALUES('AG-098','MUHAMAD AMIRUL MUZAYIN A.Md.T.','199005122025061002','Pranata Sumber Daya Manusia Aparatur Terampil Subbagian Umum','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur (II/c); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-099','YOGA ADITYA SAPUTRA NUGROHO','199606022020121005','Mandor Mesin Kapal Kelas I Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda Tk. I (II/b); TMT Golongan: 01/12/2024; TMT Jabatan: 01/05/2026',NULL);
INSERT INTO "anggota" VALUES('AG-100','FAJRI FITRAH HERMAWAN','199701182020121003','Kelasi Kapal Kelas II','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda Tk. I (II/b); TMT Golongan: 01/12/2024; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-101','HENGKI MADIYANTO','199211072020121004','Kelasi Kapal Kelas II Seksi Sumber Daya Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda Tk. I (II/b); TMT Golongan: 01/12/2024; TMT Jabatan: 01/05/2025',NULL);
INSERT INTO "anggota" VALUES('AG-102','ROHMAN SYAH','200105162020121002','Tugas Belajar Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda Tk. I (II/b); TMT Golongan: 01/12/2024; TMT Jabatan: 01/12/2022',NULL);
INSERT INTO "anggota" VALUES('AG-103','HERLAN SOULTAN ABDUL ALBAR','199012032009121002','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda Tk. I (II/b); TMT Golongan: 01/09/2025; TMT Jabatan: 01/05/2024',NULL);
INSERT INTO "anggota" VALUES('AG-104','AFIANI KUSUMA PRADANI','199606272015032001','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda Tk. I (II/b); TMT Golongan: 01/09/2025; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-105','LUTHFI ALMASYAH','200011022020121006','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/12/2021; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-106','NASRUL MAULANA WAHID','199711202020121001','Pranata Pencarian dan Pertolongan Terampil Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/12/2021; TMT Jabatan: 09/09/2026',NULL);
INSERT INTO "anggota" VALUES('AG-107','MUHAMMAD RIZKY','200101152020121003','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/12/2021; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-108','HUSNI FAJRI','199504222020121002','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/12/2021; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-109','ARRIZAL AULIA AMIR','199802192022031001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/03/2023; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-110','RESTU MANDALA','199810182022031001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/03/2023; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-111','DWI ANGGA','199607252022031001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/03/2023; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-112','FARHAN NAUFAL','200112282022031001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/03/2023; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-113','FARHAN ADITYA FA''IQ SUPRIATNO','200208082022031001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/03/2023; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-114','HENRI OKTA NUGRAHA','199910052022031001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/03/2023; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-115','MUHAMMAD GIFARI','200201152022031001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/03/2023; TMT Jabatan: 01/08/2026',NULL);
INSERT INTO "anggota" VALUES('AG-116','DINO PRASETYO','199612022022031001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/03/2023; TMT Jabatan: 01/10/2023',NULL);
INSERT INTO "anggota" VALUES('AG-117','FEBRIA SANDY NUR ISLAMIYAH','200302102022032001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/03/2023; TMT Jabatan: 01/05/2026',NULL);
INSERT INTO "anggota" VALUES('AG-118','NAFISA ZAINAL IZZAH','200403042025062001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2025; TMT Jabatan: 01/06/2025',NULL);
INSERT INTO "anggota" VALUES('AG-119','MUHAMAD RIZQY AULIA','200605072025061003','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-120','MUHAMMAD MIFTAHUL HAQ','200205222025061002','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-121','RAMADHAN SYAHWA','200012152025061004','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-122','YUSUF WILDAN AULA','200003302025061005','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-123','RICKO YOGA SAPUTRA','200405272025061002','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-124','LA ODE MUH. ALFI SYHAR','200206172025061003','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-125','MUH. KHOIRUL RIZAL','200206032025061002','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-126','YUDHISTIRA ALLANDIO BHASKARA','200308282025061001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-127','NAILA APRILIYAH','200604122025062001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-128','MOCH LUKMAN HAKIM','200001132025061005','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-129','LAZUARDI SATRIA IKRAM','200203062025061003','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-130','DEFITS RIZAL PRAYOGO','200001062025061003','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-131','ALIM MUHAMMAD FATHIR','200502172025061001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-132','CALISTA DWI DAMAYANTI','200602182025062002','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-133','ANGGI AZELIKHA RUSTANTI','200506242025062001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-134','KELVIN ALDI NUGROHO','200405102025061004','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-135','ABDI ABDULLAH MAGROBI','200107222025061004','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-136','RHEYNANDA DIAZ YUDHISTIRA','200008102025061005','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-137','DANI TRISNA ARDIANSAH','200505252025061001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-138','CHANDA VERTIKA MAULIZA','200507032025062003','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-139','KHAIRIL MUTTAQIN','200603302025061001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-140','ZULFIQAR FARIZ RAMADHAN','199912282025061005','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-141','JUANETA ARI SOFYANTI','200207072025062002','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-142','NAUFAL AZMI','200207142025061003','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-143','RENI NUR HAFIFAH','200305262025062001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-144','ALDILLAH FATHUR RIZKY','200307152025061003','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-145','M. AL GUFRON','200306042025061002','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-146','MUHAMAD FAQIH','200203212025061002','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-147','MOCHAMMAD AGUNG HAMZAH WICAKSONO','200107262025061003','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-148','FIRMANS ZANNUBA IRROCHIM','199909182025061002','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-149','CHATRYA WAHYU SHODIQ IHSANUDDIN','200307232025061001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-150','HAFID HUDAYA','200411122025061001','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
INSERT INTO "anggota" VALUES('AG-151','NADHIF DWI RENDRAGRAHA','200205162025061002','Pranata Pencarian dan Pertolongan Pemula Seksi Operasi dan Siaga Pencarian dan Pertolongan','Kantor Pencarian dan Pertolongan Kelas A Jakarta',NULL,NULL,NULL,NULL,NULL,'Aktif','Golongan: Pengatur Muda (II/a); TMT Golongan: 01/06/2026; TMT Jabatan: 11/06/2026',NULL);
CREATE TABLE app_state (
  key TEXT PRIMARY KEY,
  value TEXT
);
CREATE TABLE bimtek (
  id TEXT PRIMARY KEY,
  nama_bimtek TEXT,
  jenis_bimtek TEXT,
  materi TEXT,
  tanggal TEXT,
  waktu TEXT,
  lokasi TEXT,
  penyelenggara TEXT,
  narasumber TEXT,
  peserta TEXT,
  jumlah_peserta INTEGER,
  petugas TEXT,
  keterangan TEXT,
  dokumentasi TEXT,
  status TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);
INSERT INTO "bimtek" VALUES('BMT-001','Bimtek Vertical Rescue','Teknis','Teknik SRT & Anchor','2026-08-20','08:30','Gedung Latihan BASARNAS','Bidang Operasi','Fajar Nugroho','Regu 3',8,'AG-005',NULL,NULL,'Selesai',NULL,NULL,NULL,NULL,NULL);
CREATE TABLE diklat (
  id TEXT PRIMARY KEY,
  nama_diklat TEXT,
  jenis_diklat TEXT,
  materi TEXT,
  tanggal_mulai TEXT,
  tanggal_selesai TEXT,
  lokasi TEXT,
  penyelenggara TEXT,
  instruktur TEXT,
  peserta TEXT,
  jumlah_peserta INTEGER,
  anggota_bertugas TEXT,
  sertifikat TEXT,
  dokumentasi TEXT,
  status TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);
INSERT INTO "diklat" VALUES('DKL-001','Diklat Dasar Rescuer Angkatan 11','Diklat Dasar','Dasar-dasar SAR','2026-07-01','2026-07-14','Pusdiklat BASARNAS','Pusdiklat','Bambang Setiawan','20 calon rescuer',20,'AG-001','Diterbitkan',NULL,'Selesai',NULL,NULL,NULL,NULL,NULL);
CREATE TABLE kegiatan (
  id TEXT PRIMARY KEY,
  nomor TEXT,
  jenis TEXT REFERENCES ref_jenis_kegiatan(jenis),
  jenis_label TEXT,
  nama TEXT,
  tanggal TEXT,
  tanggal_selesai TEXT,
  waktu TEXT,
  lokasi TEXT,
  penanggung_jawab TEXT,
  no_hp TEXT,
  deskripsi TEXT,
  jumlah_dibutuhkan INTEGER,
  status_persetujuan TEXT CHECK (status_persetujuan IS NULL OR status_persetujuan IN ('Menunggu Persetujuan','Disetujui','Ditolak')),
  keterangan_persetujuan TEXT,
  status_pelaksanaan TEXT CHECK (status_pelaksanaan IS NULL OR status_pelaksanaan IN ('Draft','Dalam Pelaksanaan','Selesai')),
  status_wa TEXT,
  dokumentasi TEXT,
  surat_id TEXT,
  linked_record_id TEXT,
  lampiran_name TEXT,
  lampiran_size INTEGER,
  lampiran_data TEXT,
  dibuat TEXT,
  diperbarui TEXT,
  extra_json TEXT
);
INSERT INTO "kegiatan" VALUES('KGT-001','001/KGT/2026','sgts','Sar Go To School','Sosialisasi SAR SDN 05 Tangerang','2026-09-28','08:00','SDN 05 Tangerang','Bambang Setiawan',NULL,'Edukasi keselamatan air dan simulasi evakuasi dasar untuk siswa kelas 4-6.',3,'Menunggu Persetujuan',NULL,'Draft','Belum Terkirim',NULL,NULL,NULL,NULL,NULL,NULL,'9 Okt 2026, 06.25','9 Okt 2026, 06.25',NULL);
INSERT INTO "kegiatan" VALUES('KGT-002','002/KGT/2026','bimtek','Bimtek','Bimtek Water Rescue Regu 2','2026-09-25','09:00','Dermaga Tanjung Priok','Andi Wijaya',NULL,'Bimbingan teknis penyelamatan di perairan untuk regu 2.',4,'Disetujui','Disetujui oleh KSO — sesuai jadwal latihan rutin.','Dalam Pelaksanaan','Belum Terkirim',NULL,NULL,NULL,NULL,NULL,NULL,'9 Okt 2026, 06.25','9 Okt 2026, 06.25',NULL);
INSERT INTO "kegiatan" VALUES('KGT-003','003/KGT/2026','uji_periodik','Uji Periodik','Uji Periodik Peralatan Selam','2026-09-18','13:00','Gudang Peralatan Pusat','Fajar Nugroho',NULL,'Pengujian berkala tabung selam dan regulator sebelum operasi.',2,'Disetujui','Disetujui oleh KKS.','Selesai','Belum Terkirim',NULL,NULL,NULL,NULL,NULL,NULL,'9 Okt 2026, 06.25','9 Okt 2026, 06.25',NULL);
INSERT INTO "kegiatan" VALUES('KGT-004','004/KGT/2026','diklat','Diklat','Diklat Dasar Rescuer Angkatan 12','2026-09-10','08:00','Pusdiklat BASARNAS','Hendra Gunawan',NULL,'Pengajuan diklat dasar ditolak karena bentrok dengan jadwal siaga nasional.',6,'Ditolak','Ditolak oleh KSD — bentrok dengan status siaga nasional pada tanggal terkait.','Draft','Belum Terkirim',NULL,NULL,NULL,NULL,NULL,NULL,'9 Okt 2026, 06.25','9 Okt 2026, 06.25',NULL);
CREATE TABLE kegiatan_anggota (
  kegiatan_id TEXT NOT NULL REFERENCES kegiatan(id) ON DELETE CASCADE,
  anggota_id TEXT NOT NULL,
  PRIMARY KEY (kegiatan_id, anggota_id)
);
CREATE TABLE kegiatan_file (
  fid TEXT PRIMARY KEY,
  kegiatan_id TEXT NOT NULL REFERENCES kegiatan(id) ON DELETE CASCADE,
  kind TEXT NOT NULL CHECK (kind IN ('sprin','bukti','hasil')),
  name TEXT,
  size INTEGER,
  mime TEXT,
  uploaded TEXT,
  oleh TEXT,
  laporan INTEGER DEFAULT 0,
  path TEXT
);
CREATE TABLE kegiatan_wa_log (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  kegiatan_id TEXT NOT NULL REFERENCES kegiatan(id) ON DELETE CASCADE,
  waktu TEXT,
  jumlah INTEGER
);
CREATE TABLE kendaraan (
  id TEXT PRIMARY KEY,
  nama_kendaraan TEXT,
  sapras TEXT,
  jenis_kendaraan TEXT,
  nomor_polisi TEXT,
  merk_tipe TEXT,
  kondisi TEXT,
  lokasi TEXT,
  tanggal TEXT,
  km_saat_ini REAL,
  bbm REAL,
  endurance REAL,
  speed REAL,
  range REAL,
  konsumsi_bbm REAL,
  cadangan_bbm REAL,
  persen_bbm REAL,
  kecepatan_angin REAL,
  arah_angin TEXT,
  keterangan TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);
CREATE TABLE lainnya (
  id TEXT PRIMARY KEY,
  nama_kegiatan TEXT,
  jenis_kegiatan TEXT,
  tanggal TEXT,
  waktu TEXT,
  lokasi TEXT,
  penyelenggara TEXT,
  penanggung_jawab TEXT,
  peserta TEXT,
  jumlah_peserta INTEGER,
  anggota_bertugas TEXT,
  keterangan TEXT,
  dokumentasi TEXT,
  status TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);
CREATE TABLE narasumber (
  id TEXT PRIMARY KEY,
  nama TEXT,
  instansi TEXT,
  jabatan TEXT,
  keahlian TEXT,
  nomor_kontak TEXT,
  email TEXT,
  materi TEXT,
  kegiatan_terkait TEXT,
  status TEXT,
  keterangan TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);
INSERT INTO "narasumber" VALUES('NRS-001','Dr. Arief Kurniawan','Kemenkes RI','Dokter Spesialis Emergency','Medical Emergency Response','+62 811-3000-0001','arief.k@kemenkes.go.id','Penanganan Korban Bencana','Diklat, Bimtek','Direncanakan',NULL,NULL,NULL,NULL,NULL,NULL);
CREATE TABLE pemberdayaan (
  id TEXT PRIMARY KEY,
  nama_kegiatan TEXT,
  sasaran TEXT,
  lokasi TEXT,
  tanggal TEXT,
  jumlah_peserta INTEGER,
  materi TEXT,
  narasumber TEXT,
  petugas TEXT,
  hasil TEXT,
  dokumentasi TEXT,
  status TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);
INSERT INTO "pemberdayaan" VALUES('PBM-001','Pemberdayaan Nelayan Tangguh Bencana','Kelompok Nelayan Muara Angke','Muara Angke','2026-08-22',40,'Mitigasi Bencana Laut','Dr. Arief Kurniawan','AG-004','40 nelayan terlatih dasar mitigasi',NULL,'Selesai',NULL,NULL,NULL,NULL,NULL);
CREATE TABLE pembinaan (
  id TEXT PRIMARY KEY,
  nama_kegiatan TEXT,
  jenis_pembinaan TEXT,
  sasaran TEXT,
  tanggal TEXT,
  lokasi TEXT,
  materi TEXT,
  pembina TEXT,
  peserta TEXT,
  anggota_bertugas TEXT,
  hasil TEXT,
  dokumentasi TEXT,
  status TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);
INSERT INTO "pembinaan" VALUES('PBN-001','Pembinaan Potensi SAR Wilayah Barat','Rutin','Kelompok Potensi SAR','2026-08-05','Balai Desa Cengkareng','Standar Prosedur Evakuasi','Bambang Setiawan','25 orang','AG-001, AG-005','Peserta memahami SOP dasar',NULL,'Selesai',NULL,NULL,NULL,NULL,NULL);
CREATE TABLE peminjaman (
  id TEXT PRIMARY KEY,
  nama_peminjam TEXT,
  jenis_barang TEXT,
  nama_barang TEXT,
  jumlah INTEGER,
  tanggal TEXT,
  tanggal_kembali_rencana TEXT,
  tanggal_kembali TEXT,
  keperluan TEXT,
  penanggung_jawab TEXT,
  status TEXT,
  keterangan TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);
CREATE TABLE peralatan (
  id TEXT PRIMARY KEY,
  nama_barang TEXT,
  kode_rak TEXT,
  lokasi TEXT,
  kategori TEXT,
  jumlah_baik INTEGER,
  jumlah_rusak INTEGER,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);
CREATE TABLE peserta_kegiatan (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  modul TEXT NOT NULL CHECK (modul IN ('diklat','uji_periodik')),
  record_id TEXT NOT NULL,
  anggota_id TEXT,
  nama TEXT,
  jabatan TEXT,
  jk TEXT,
  data_json TEXT
);
CREATE TABLE potensi (
  id TEXT PRIMARY KEY,
  nama_potensi TEXT,
  kategori TEXT,
  nama_kontak TEXT,
  nomor_kontak TEXT,
  alamat TEXT,
  wilayah TEXT,
  kompetensi TEXT,
  jumlah_tersertifikasi INTEGER,
  jumlah_belum_sertifikasi INTEGER,
  status TEXT,
  alut_sapras TEXT,
  keterangan TEXT,
  dokumentasi TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);
INSERT INTO "potensi" VALUES('POT-001','Komunitas Selam Jakarta','Organisasi','Rudi Hartono','+62 813-2000-0001','Jakarta Utara','DKI Jakarta','Selam dasar-lanjut',14,6,'Aktif','2 unit perahu karet, alat selam lengkap','Mitra potensial SAR',NULL,NULL,NULL,NULL,NULL,'{"jumlah_total":20}');
CREATE TABLE ref_jenis_kegiatan (
  jenis TEXT PRIMARY KEY,
  label TEXT NOT NULL
);
INSERT INTO "ref_jenis_kegiatan" VALUES('sgts','Sar Go To School');
INSERT INTO "ref_jenis_kegiatan" VALUES('bimtek','Bimtek');
INSERT INTO "ref_jenis_kegiatan" VALUES('potensi','Potensi');
INSERT INTO "ref_jenis_kegiatan" VALUES('narasumber','Narasumber');
INSERT INTO "ref_jenis_kegiatan" VALUES('diklat','Diklat');
INSERT INTO "ref_jenis_kegiatan" VALUES('rescuer','Rescuer');
INSERT INTO "ref_jenis_kegiatan" VALUES('pembinaan','Pembinaan Tenaga SAR');
INSERT INTO "ref_jenis_kegiatan" VALUES('pemberdayaan','Pemberdayaan Masyarakat');
INSERT INTO "ref_jenis_kegiatan" VALUES('uji_periodik','Uji Periodik');
INSERT INTO "ref_jenis_kegiatan" VALUES('uji_pemeliharaan','Uji Pembinaan & Pemeliharaan');
INSERT INTO "ref_jenis_kegiatan" VALUES('lainnya','Lainnya');
CREATE TABLE rescuer (
  id TEXT PRIMARY KEY,
  anggota_id TEXT,
  jabatan TEXT,
  kompetensi TEXT,
  spesialisasi TEXT,
  sertifikasi TEXT,
  masa_berlaku TEXT,
  status TEXT,
  riwayat TEXT,
  keterangan TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);
INSERT INTO "rescuer" VALUES('RSC-001','AG-033','Kepala Regu','Water Rescue','Selam','BRLS','2027-03-01','Aktif','12 penugasan tahun ini',NULL,NULL,NULL,NULL,NULL,NULL);
CREATE TABLE sgts (
  id TEXT PRIMARY KEY,
  nama_sekolah TEXT,
  alamat_sekolah TEXT,
  tanggal TEXT,
  waktu TEXT,
  jumlah_peserta INTEGER,
  materi TEXT,
  penanggung_jawab TEXT,
  narasumber TEXT,
  anggota_bertugas TEXT,
  keterangan TEXT,
  dokumentasi TEXT,
  status TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);
INSERT INTO "sgts" VALUES('SGS-001','SMPN 12 Bekasi','Jl. Raya Bekasi Timur','2026-08-14','09:00',120,'Pengenalan SAR & Water Safety','Siti Rahmawati','Andi Wijaya','AG-002, AG-003','Kegiatan tahunan',NULL,'Selesai',NULL,NULL,NULL,NULL,NULL);
CREATE TABLE surat_masuk (
  id TEXT PRIMARY KEY,
  nomor_surat TEXT,
  asal_surat TEXT,
  tanggal_surat TEXT,
  tanggal_diterima TEXT,
  perihal TEXT,
  jenis TEXT REFERENCES ref_jenis_kegiatan(jenis),
  jenis_label TEXT,
  tanggal_kegiatan TEXT,
  waktu TEXT,
  lokasi TEXT,
  penanggung_jawab TEXT,
  no_hp TEXT,
  jumlah_dibutuhkan INTEGER,
  deskripsi TEXT,
  file_name TEXT,
  file_size INTEGER,
  file_data TEXT,
  status TEXT CHECK (status IS NULL OR status IN ('Menunggu Persetujuan','Disetujui','Ditolak')),
  keterangan_persetujuan TEXT,
  kegiatan_id TEXT,
  dibuat TEXT,
  diperbarui TEXT,
  extra_json TEXT
);
CREATE TABLE surat_masuk_acc (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  surat_id TEXT NOT NULL REFERENCES surat_masuk(id) ON DELETE CASCADE,
  role TEXT NOT NULL,
  waktu TEXT
);
CREATE TABLE uji_imt (
  uji_id TEXT NOT NULL,
  anggota_id TEXT NOT NULL,
  jawaban_json TEXT,
  usia REAL,
  tb REAL,
  bb REAL,
  imt REAL,
  kategori TEXT,
  td REAL,
  nadi REAL,
  tanggal TEXT,
  diperbarui TEXT,
  extra_json TEXT,
  PRIMARY KEY (uji_id, anggota_id)
);
CREATE TABLE uji_kategori (
  id TEXT PRIMARY KEY,
  kategori TEXT,
  uji_id TEXT,
  anggota_id TEXT,
  nama TEXT,
  jabatan TEXT,
  jk TEXT,
  rata_rata REAL,
  predikat TEXT,
  dibuat TEXT,
  diperbarui TEXT,
  extra_json TEXT
);
CREATE TABLE uji_pemeliharaan (
  id TEXT PRIMARY KEY,
  nama_peralatan TEXT,
  nomor_inventaris TEXT,
  jenis_peralatan TEXT,
  tanggal_pemeriksaan TEXT,
  kondisi TEXT,
  jenis_pemeliharaan TEXT,
  petugas TEXT,
  hasil TEXT,
  tindakan TEXT,
  jadwal_berikutnya TEXT,
  keterangan TEXT,
  dokumentasi TEXT,
  status TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);
INSERT INTO "uji_pemeliharaan" VALUES('UPM-001','Perahu Karet RIB-02','INV-PRH-002','Perahu','2026-08-10','Baik','Perawatan rutin','Hendra Gunawan','Mesin dan lambung dalam kondisi baik','Ganti oli mesin','2026-11-10',NULL,NULL,'Baik',NULL,NULL,NULL,NULL,NULL);
CREATE TABLE uji_periodik (
  id TEXT PRIMARY KEY,
  nama_uji TEXT,
  jenis_uji TEXT,
  materi TEXT,
  tanggal_mulai TEXT,
  tanggal_selesai TEXT,
  lokasi TEXT,
  penyelenggara TEXT,
  penguji TEXT,
  peserta TEXT,
  jumlah_peserta INTEGER,
  anggota_bertugas TEXT,
  sertifikat TEXT,
  dokumentasi TEXT,
  status TEXT,
  dari_kegiatan_id TEXT,
  tahun_input INTEGER,
  bulan_input INTEGER,
  tanggal_input TEXT,
  extra_json TEXT
);
INSERT INTO "uji_periodik" VALUES('UPR-001','Uji Periodik Kompetensi Selam Angkatan 1','Uji Kompetensi','Penyelaman dan penyelamatan air','2026-08-01','2026-08-02','Gudang Peralatan Pusat','Kantor SAR Jakarta','Fajar Nugroho','Tim Rescuer Selam',NULL,NULL,NULL,NULL,'Layak',NULL,NULL,NULL,NULL,NULL);
CREATE INDEX idx_kegiatan_tanggal ON kegiatan(tanggal);
CREATE INDEX idx_kegiatan_status ON kegiatan(status_persetujuan, status_pelaksanaan);
CREATE INDEX idx_kegiatan_anggota ON kegiatan_anggota(anggota_id);
CREATE INDEX idx_peserta ON peserta_kegiatan(modul, record_id);
CREATE INDEX idx_uji_kat ON uji_kategori(uji_id, kategori);
CREATE VIEW v_potensi AS SELECT *, COALESCE(jumlah_tersertifikasi,0)+COALESCE(jumlah_belum_sertifikasi,0) AS jumlah_total FROM potensi;
CREATE VIEW v_rescuer AS SELECT a.id AS anggota_id, a.nama AS nama_anggota, a.nip, r.* FROM rescuer r JOIN anggota a ON a.id=r.anggota_id;
CREATE VIEW v_peralatan AS SELECT p.*, COALESCE(jumlah_baik,0)+COALESCE(jumlah_rusak,0) AS jumlah,
  MAX(0, COALESCE(jumlah_baik,0)+COALESCE(jumlah_rusak,0) - COALESCE((SELECT SUM(COALESCE(m.jumlah,1)) FROM peminjaman m
   WHERE m.jenis_barang='Peralatan' AND lower(trim(m.nama_barang))=lower(trim(p.nama_barang)) AND m.status<>'Dikembalikan' AND m.tanggal_kembali IS NULL),0)) AS tersedia
FROM peralatan p;
CREATE VIEW v_kegiatan_anggota AS SELECT ka.kegiatan_id, k.nama AS kegiatan, k.jenis, a.id AS anggota_id, a.nama
FROM kegiatan_anggota ka JOIN kegiatan k ON k.id=ka.kegiatan_id JOIN anggota a ON a.id=ka.anggota_id;
DELETE FROM "sqlite_sequence";
COMMIT;

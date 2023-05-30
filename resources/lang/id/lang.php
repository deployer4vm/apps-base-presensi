<?php
/**
 * GENERAL TEMPLATE LANGUAGE
 */
return [
    'home' => 'Beranda',
    //-- general heading caption 
    'form' => 'Form',
    'form_attribute' => 'Form :attribute',
    'add' => 'Tambah',
    'add_new' => 'Tambah Baru',
    'add_attribute' => 'Tambah :attribute baru',
    'edit' => 'Edit',
    'edit_attribute' => 'Edit :attribute',
    //-- general button caption
    'save_change' => 'Simpan Perubahan',
    'save' => 'Simpan',
    'change' => 'Ubah',
    'reset' => 'Reset',
    'cancel' => 'Batal',
    'no' => 'Tidak',
    'not' => 'Bukan',
    'close' => 'Tutup',
    'ok' => 'Ok',
    'yes' => 'Ya',
    'done' => 'Done',
    'exit' => 'Keluar',
    'back' => 'Kembali',
    'delete' => 'Hapus',
    'search' => 'Cari',
    'view' => 'Tampilkan',
    'view_all' => 'Tampilkan Semua',
    'view_detail' => 'Tampilkan Detail',
    'filter' => 'Filter',
    'keyword' => 'Kata kunci...',
    'upload' => 'Upload',
    'upload_attribute' => 'Upload :attribute',
    'download' => 'Download',
    'downlaod_attribute' => 'Download :attribute',
    'menu' => 'Menu',
    'manage_attribute' => 'Manage :attribute',
    'preview' => 'Preview',
    'print' => 'Print',
    'approve' => 'Approve',
    'reject' => 'Reject',
    //-- general caption
    'keyword' => 'Kata kunci...',
    'loading_data' => 'Loading',
    'data_not_found' => 'Data tidak ditemukan',
    'data_attribute_not_found' => 'Data :attribute tidak ditemukan',
    'data_attribute_cannot_be_empty' => 'Data :attribute tidak boleh kosong',
    'no_data' => 'Belum ada data',
    //-- general label
    'detail' => 'Detail',
    'status' => 'Status',
    'select' => 'Pilih',
    'active' => 'Aktif',
    'inactive' => 'Tidak Aktif',
    'publish' => 'Publish',
    'draft' => 'Draft',
    'disable' => 'Disable',
    'enable' => 'Enable',
    'file' => 'File',
    'no_file' => 'Tidak ada file',
    //-- home template    
    'system_app_loading_text' => 'Loading application data...',
    'system_app_load_button_text' => 'Reload',
    //-- date
    'date' => 'Tanggal',
    'day' => 'Hari',
    'week' => 'Minggu',
    'month' => 'Bulan',
    'year' => 'Tahun',
    'day_label' => [
        'monday' => 'Senin',
        'tuesday' => 'Selasa',
        'wednesday' => 'Rabu',
        'thursday' => 'Kamis',
        'friday' => 'Jumat',
        'saturday' => 'Sabtu',
        'sunday' => 'Minggu',
    ],
    'month_label' => [
        'january' => 'Januari',
        'february' => 'Februari',
        'march' => 'Maret',
        'april' => 'April',
        'may' => 'Mei',
        'june' => 'Juni',
        'july' => 'Juli',
        'august' => 'Agustus',
        'september' => 'September',
        'october' => 'Oktober',
        'november' => 'November',
        'december' => 'Desember'
    ],

    //-- general table
    'table' => [
        'header' => [
            'filter' => [
                'range_start' => 'Tanggal Awal',
                'range_end' => 'Tanggal Akhir'
            ]
        ],
        'column_name' => [
            'no'=>'No',
            'insert_time' => 'Tanggal Dibuat',
            'status'=>'Status',
            'total'=>'Total',
            'sub_total'=>'Sub Total',
            'action'=>'Aksi'
        ],
        'label' => [],
    ],

    //-- general form
    'form' => [
        'action' => [],
        'label' => [],
    ],

    //-- Import caption
    'import' => [
        'name' => 'Import',
        'alert' => [
            'start_proccess' => 'File import sedang diproses, mohon tunggu...',
            'import_failed' => ' Import gagal',
            'approve_progress' => 'Approve on progress',
            'cancel_progress' => ' Cancel import on progress',
            'proccess_upload' => 'File import berhasil diupload dan sedang diproses, silahkan tunggu hingga proses import selesai',
            'upload_failed' => 'Upload file import gagal',
            'import_finish' => 'Import selesai',
            'access_failed' => 'Access status import gagal',
            'approved' => 'Data import diapprove',
            'request_approve_failed' => 'Request Approve gagal',
            'cancel' => 'Data import dibatalkan',
            'request_cancel_failed' => 'Request pembatalan gagal',
        ],
        'label' => [
            'caption_attribute' => 'Import :attribute',
            'data_import' => 'Data yang diimport',
            'upload_file' => 'Upload File Import',
            'import_date' => 'Tanggal Import',
            'data_count' => 'Data count',
            'proccess_count' => 'Processed count',
            'download_format' => 'Download Format File Import',
            'guide' => 'Panduan',
            'guide_description' => 'Silahkan download format file yang telah disediakan, dan jangan mengubah struktur kolom dan urutan baris data.',
            'last_log' => 'Log import terkahir',
            'empty_file' => 'belum ada file import',
            'approve' => 'Approve',
            'cancel' => 'Cancel Import',
            'approve_description' => 'Jika data telah sesuai maka silahkan <b>Approve</b> untuk menambahkan data hasil import, jika belum sesuai silahkan <b>Cancel Import</b> dan ulang proses import',
        ],
    ],

    //-- export caption
    'export' => [
        'name' => 'Export',
        'alert' => [
            'start_proccess' => 'File export sedang disiapkan untuk didownload, tunggu hingga proses selesai.',
            'finish_proccess' => 'File export telah selesai dipersiapkan, silahkan didownload.',
            'generate_failed' => 'Generate download gagal',
            'access_failed' => 'Access status download gagal',
        ],
        'label' => [
            'file' => 'File download',
            'generate_date' => 'Tanggal generate',
            'generate_new' => 'Generate Download Terbaru',
            'generate_proccess' => 'File download sedang digenerate, mohon tunggu...',
            'data_count' => 'Data count',
            'proccess_count' => 'Processed count',
            'last_log' => 'Log download terkahir',
            'empty_file' => 'belum ada file download',
            'by_filter' => 'Download sesuai filter',
        ],
    ]
];

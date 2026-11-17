<?php

// Static Nepal reference data for KYC address and payout forms.
// shortcut: the 753 local levels (municipalities) are free text; add a list if address quality suffers.
return [
    'provinces' => [
        'Koshi Province',
        'Madhesh Province',
        'Bagmati Province',
        'Gandaki Province',
        'Lumbini Province',
        'Karnali Province',
        'Sudurpashchim Province',
    ],

    'districts' => [
        'Koshi Province' => ['Bhojpur', 'Dhankuta', 'Ilam', 'Jhapa', 'Khotang', 'Morang', 'Okhaldhunga', 'Panchthar', 'Sankhuwasabha', 'Solukhumbu', 'Sunsari', 'Taplejung', 'Terhathum', 'Udayapur'],
        'Madhesh Province' => ['Bara', 'Dhanusha', 'Mahottari', 'Parsa', 'Rautahat', 'Saptari', 'Sarlahi', 'Siraha'],
        'Bagmati Province' => ['Bhaktapur', 'Chitwan', 'Dhading', 'Dolakha', 'Kathmandu', 'Kavrepalanchok', 'Lalitpur', 'Makwanpur', 'Nuwakot', 'Ramechhap', 'Rasuwa', 'Sindhuli', 'Sindhupalchok'],
        'Gandaki Province' => ['Baglung', 'Gorkha', 'Kaski', 'Lamjung', 'Manang', 'Mustang', 'Myagdi', 'Nawalpur', 'Parbat', 'Syangja', 'Tanahun'],
        'Lumbini Province' => ['Arghakhanchi', 'Banke', 'Bardiya', 'Dang', 'Eastern Rukum', 'Gulmi', 'Kapilvastu', 'Palpa', 'Parasi', 'Pyuthan', 'Rolpa', 'Rupandehi'],
        'Karnali Province' => ['Dailekh', 'Dolpa', 'Humla', 'Jajarkot', 'Jumla', 'Kalikot', 'Mugu', 'Salyan', 'Surkhet', 'Western Rukum'],
        'Sudurpashchim Province' => ['Achham', 'Baitadi', 'Bajhang', 'Bajura', 'Dadeldhura', 'Darchula', 'Doti', 'Kailali', 'Kanchanpur'],
    ],

    // Nepal Rastra Bank class "A" commercial banks.
    'banks' => [
        'Agricultural Development Bank', 'Citizens Bank International', 'Everest Bank', 'Global IME Bank',
        'Himalayan Bank', 'Kumari Bank', 'Laxmi Sunrise Bank', 'Machhapuchchhre Bank', 'Nabil Bank',
        'Nepal Bank', 'Nepal Investment Mega Bank', 'Nepal SBI Bank', 'NIC Asia Bank', 'NMB Bank',
        'Prabhu Bank', 'Prime Commercial Bank', 'Rastriya Banijya Bank', 'Sanima Bank', 'Siddhartha Bank',
        'Standard Chartered Bank Nepal',
    ],
];

<?php
/**
 * ProjectSphere - SMTP Configuration
 * 
 * To send real emails directly to inboxes (Gmail/Outlook/Yahoo):
 * 1. Set 'username' to your Gmail address (e.g. 'your_name@gmail.com')
 * 2. Set 'password' to your 16-character Google App Password (not your regular account password)
 *    -> Get Google App Password from: https://myaccount.google.com/apppasswords
 */
return [
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'username'   => '',  // Enter your Gmail address here
    'password'   => '',  // Enter your 16-character Google App Password here
    'from_email' => 'no-reply@projectsphere.edu',
    'from_name'  => 'ProjectSphere Security Portal',
];

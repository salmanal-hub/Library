<?php

/**
 * Index Page - Entry point for the Digital Library Management System
 * 
 * This page will redirect users to the appropriate page based on their login status
 */

require_once 'config.php';

// Check if user is already logged in
if (isLoggedIn()) {
    // User is logged in, redirect to dashboard
    redirect('dashboard.php');
} else {
    // User is not logged in, redirect to login page
    redirect('login.php');
}

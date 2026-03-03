<?php
// Include this at the top of your PHP file

// Enable error logging for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure upload directory exists
$upload_dir = "uploads/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
    // Also create .htaccess to ensure directory is accessible
    file_put_contents($upload_dir . '.htaccess', "Options +Indexes\nOrder Allow,Deny\nAllow from all");
}

// Helper function to convert a file path to base64 data URL
function getBase64Image($imagePath) {
    if (!file_exists($imagePath)) {
        error_log("Image file does not exist: " . $imagePath);
        return '';
    }
    
    // Get image content and mime type
    $imageData = file_get_contents($imagePath);
    if ($imageData === false) {
        error_log("Failed to read image data from: " . $imagePath);
        return '';
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $imagePath);
    finfo_close($finfo);
    
    // Convert to base64
    $base64 = base64_encode($imageData);
    return 'data:' . $mimeType . ';base64,' . $base64;
}

// Variable to store status messages and HTML preview
$message = '';
$preview_html = '';
$current_step = 1;

// Process the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("Form submitted. Processing file uploads...");
    
    // Determine current step based on form data
    if (isset($_POST['body_content']) && !empty($_POST['body_content'])) {
        $current_step = 2;
    }
    
    if (isset($_POST['layout_type']) && $_POST['layout_type'] !== '0') {
        $current_step = 3;
    }
    
    if (isset($_POST['regards_name']) && !empty($_POST['regards_name'])) {
        $current_step = 4;
    }
    
    // Process header image
    $header_image = '';
    $header_image_data = '';
    
    if (isset($_FILES["header_image"]) && $_FILES["header_image"]["error"] == 0) {
        $target_file = $upload_dir . basename($_FILES["header_image"]["name"]);
        error_log("Processing header image: " . $target_file);
        
        // Verify it's an image
        $check = getimagesize($_FILES["header_image"]["tmp_name"]);
        if ($check !== false) {
            // Move the uploaded file
            if (move_uploaded_file($_FILES["header_image"]["tmp_name"], $target_file)) {
                error_log("Header image uploaded successfully");
                $header_image = $target_file;
                $header_image_data = getBase64Image($header_image);
            } else {
                error_log("Failed to move uploaded header image: " . error_get_last()['message']);
                $message = "Failed to save header image. Please try again.";
            }
        } else {
            error_log("File is not an image");
            $message = "The uploaded header file is not a valid image.";
        }
    } else if (isset($_FILES["header_image"])) {
        error_log("Header image upload error: " . $_FILES["header_image"]["error"]);
        switch ($_FILES["header_image"]["error"]) {
            case UPLOAD_ERR_INI_SIZE:
                $message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $message = "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form";
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = "The uploaded file was only partially uploaded";
                break;
            case UPLOAD_ERR_NO_FILE:
                // This is common, just ignore
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = "Missing a temporary folder";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = "Failed to write file to disk";
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = "File upload stopped by extension";
                break;
            default:
                $message = "Unknown upload error";
                break;
        }
    }
    
    // Process body content
    $body_content = isset($_POST['body_content']) ? $_POST['body_content'] : '';
    
    // Get signature and layout information
    $signature_name = isset($_POST['regards_name']) ? $_POST['regards_name'] : '';
    $signature_title = isset($_POST['regards_title']) ? $_POST['regards_title'] : '';
    $regards_text = isset($_POST['regards_text']) ? $_POST['regards_text'] : 'Best Regards,';
    $layout_type = isset($_POST['layout_type']) ? $_POST['layout_type'] : '0';
    
    // Process employee/group images based on layout type
    $employee_images = [];
    $employee_details = [];
    $group_image = '';
    $group_caption = '';
    $group_image_data = '';
    
    if ($layout_type === 'group') {
        // Process group image
        if (isset($_FILES["group_image"]) && $_FILES["group_image"]["error"] == 0) {
            $target_file = $upload_dir . basename($_FILES["group_image"]["name"]);
            if (getimagesize($_FILES["group_image"]["tmp_name"]) !== false) {
                if (move_uploaded_file($_FILES["group_image"]["tmp_name"], $target_file)) {
                    $group_image = $target_file;
                    $group_image_data = getBase64Image($group_image);
                    $group_caption = isset($_POST["group_caption"]) ? $_POST["group_caption"] : '';
                    error_log("Group image uploaded successfully");
                } else {
                    error_log("Failed to move uploaded group image");
                }
            }
        }
    } else {
        // Process employee images - make them optional
        $maxEmployees = 0;
        switch($layout_type) {
            case '1': $maxEmployees = 1; break;
            case '2': $maxEmployees = 2; break;
            case '3': $maxEmployees = 3; break;
            case '2-2': $maxEmployees = 4; break;
            case '3-2': $maxEmployees = 5; break;
            case '3-3': $maxEmployees = 9; break;
            default: $maxEmployees = 0;
        }
        
        // Process all potential employee slots
        for ($i = 1; $i <= $maxEmployees; $i++) {
            // Initialize employee details even if no image is uploaded
            $hasDetails = isset($_POST["employee_name_" . $i]) && !empty($_POST["employee_name_" . $i]);
            
            if ($hasDetails) {
                $employee_details[] = [
                    'name' => $_POST["employee_name_" . $i],
                    'title' => isset($_POST["employee_title_" . $i]) ? $_POST["employee_title_" . $i] : ''
                ];
            } else {
                // If there's no name, add empty details as placeholder
                $employee_details[] = [
                    'name' => '',
                    'title' => ''
                ];
            }
            
            // Check if image was uploaded for this employee
            if (isset($_FILES["employee_image_" . $i]) && $_FILES["employee_image_" . $i]["error"] == 0) {
                $target_file = $upload_dir . basename($_FILES["employee_image_" . $i]["name"]);
                if (getimagesize($_FILES["employee_image_" . $i]["tmp_name"]) !== false) {
                    if (move_uploaded_file($_FILES["employee_image_" . $i]["tmp_name"], $target_file)) {
                        $employee_images[] = getBase64Image($target_file);
                        error_log("Employee image $i uploaded successfully");
                    } else {
                        // Failed to move file, add empty placeholder
                        $employee_images[] = '';
                    }
                } else {
                    // Not a valid image, add empty placeholder
                    $employee_images[] = '';
                }
            } else {
                // No image uploaded, add empty placeholder
                $employee_images[] = '';
            }
        }
    }
    
    // Generate the email template
    if (!empty($body_content)) {
        error_log("Generating email template...");
        
        // Options for the email template
        $options = [
            'header_image' => $header_image_data,
            'body_content' => $body_content,
            'signature_name' => $signature_name,
            'signature_title' => $signature_title,
            'layout_type' => $layout_type,
            'regards_text' => $regards_text
        ];
        
        if ($layout_type === 'group') {
            $options['group_image'] = $group_image_data;
            $options['group_caption'] = $group_caption;
        } else {
            $options['employee_images'] = $employee_images;
            $options['employee_details'] = $employee_details;
        }
        
        // Generate the template
        $preview_html = generateEmailTemplate($options);
        $message = "Template generated successfully!";
        error_log("Email template generated successfully");
        $current_step = 4; // Set to completion step
    } else {
        error_log("No body content provided");
        $message = "Please provide email content.";
    }
    
    // Debug output
    error_log("Form processing complete. Status: " . $message);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Email Template Generator</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/2.3.0/alpine.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.7.0/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --secondary-color: #0ea5e9;
            --accent-color: #8b5cf6;
            --success-color: #10b981;
            --error-color: #ef4444;
            --dark: #1e293b;
            --light: #f8fafc;
            --border-radius: 0.75rem;
            --box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
        }

        * {
    font-family: 'Proxima Nova', Arial, sans-serif;
    transition: var(--transition);
}

strong, em, b, i, span, div, p, h1, h2, h3, h4, h5, h6, a, li, td, th {
    font-family: 'Proxima Nova', Arial, sans-serif;
}

        body {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            color: var(--dark);
            min-height: 100vh;
        }

        .app-header {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            padding: 1.5rem;
            color: white;
            box-shadow: var(--box-shadow);
        }

        .main-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            max-width: 1800px;
            margin: 0 auto;
            padding: 2rem;
            min-height: calc(100vh - 80px);
        }

        .form-section {
            overflow-y: auto;
            padding-right: 1rem;
        }

        .preview-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            overflow-y: auto;
            box-shadow: var(--box-shadow);
        }

        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            padding: 2rem;
            margin-bottom: 1.5rem;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header-icon {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #475569;
        }

        .form-control {
            width: 98%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            transition: var(--transition);
            font-size: 0.95rem;
            background-color: #f8fafc;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
            background-color: white;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: var(--transition);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(90deg, var(--primary-hover), var(--secondary-color));
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3), 0 4px 6px -2px rgba(79, 70, 229, 0.2);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: white;
            color: var(--dark);
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-success {
            background: linear-gradient(90deg, var(--success-color), #059669);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3), 0 4px 6px -2px rgba(16, 185, 129, 0.2);
            transform: translateY(-2px);
        }

        .btn-error {
            background: linear-gradient(90deg, var(--error-color), #dc2626);
            color: white;
        }

        .btn-error:hover {
            box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.3);
        }

        .file-upload {
            position: relative;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 2px dashed #bfdbfe;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            flex-direction: column;
        }

        .file-upload-label:hover {
            background: linear-gradient(135deg, #e0f2fe, #bfdbfe);
            border-color: var(--primary-color);
        }

        .file-upload-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .file-upload input[type="file"] {
            position: absolute;
            width: 0;
            height: 0;
            opacity: 0;
        }

        .selected-file {
            margin-top: 0.5rem;
            padding: 0.5rem;
            font-size: 0.9rem;
            color: #475569;
            background: #f1f5f9;
            border-radius: 0.25rem;
            display: flex;
            align-items: center;
        }

        .selected-file i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }

        .preview-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 3rem;
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: var(--border-radius);
            text-align: center;
        }

        .preview-empty i {
            font-size: 4rem;
            color: #94a3b8;
            margin-bottom: 1.5rem;
        }

        .preview-empty p:first-of-type {
            font-size: 1.5rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.5rem;
        }

        .preview-empty p:last-of-type {
            color: #64748b;
        }

        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            box-shadow: var(--box-shadow);
            z-index: 1000;
            max-width: 350px;
        }

        .toast i {
            margin-right: 0.75rem;
            font-size: 1.25rem;
        }

        .fade-in {
            animation: fadeIn 0.3s ease-in-out forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .image-preview {
            margin-top: 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .image-preview img {
            border-radius: 0.5rem;
            box-shadow: var(--box-shadow);
            max-width: 250px;
            max-height: 250px;
            object-fit: cover;
            margin-bottom: 0.75rem;
        }

        .manual-copy-instructions {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .manual-copy-instructions h3 {
            color: #1e40af;
            margin-bottom: 0.75rem;
        }

        .manual-copy-instructions ol {
            margin-left: 1.5rem;
        }

        .manual-copy-instructions li {
            margin-bottom: 0.5rem;
        }

        kbd {
            background: #e2e8f0;
            border-radius: 0.25rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            font-family: monospace;
        }

        #formatted-content {
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            padding: 1rem;
        }

        .spinner {
            width: 1.25rem;
            height: 1.25rem;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .progress-stepper {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 1rem;
            left: 50%;
            width: 100%;
            height: 2px;
            background-color: #e2e8f0;
            z-index: 0;
        }

        .step.active:not(:last-child)::after {
            background-color: var(--primary-color);
        }

        .step-circle {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background-color: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            position: relative;
            z-index: 1;
        }

        .step.active .step-circle {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .step.completed .step-circle {
            background: var(--success-color);
            color: white;
        }

        .step-title {
            font-size: 0.875rem;
            color: #64748b;
        }

        .step.active .step-title {
            color: var(--primary-color);
            font-weight: 500;
        }

        .step.completed .step-title {
            color: var(--success-color);
        }

        /* Debug styles */
        .debug-info {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.875rem;
            color: #475569;
        }

        .debug-info h4 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .debug-error {
            color: var(--error-color);
            font-weight: 500;
        }

        /* Media queries for responsive design */
        @media (max-width: 1280px) {
            .main-container {
                grid-template-columns: 1fr;
                height: auto;
            }

            .preview-section {
                margin-top: 2rem;
                height: 500px;
            }
        }

        @media (max-width: 768px) {
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .card-header-icon {
                margin-bottom: 1rem;
            }

            .progress-stepper {
                display: none;
            }
        }

        @media (max-width: 640px) {
            .main-container {
                padding: 1rem;
            }

            .card {
                padding: 1.5rem;
            }

            .preview-section {
                padding: 1.5rem;
            }
        }
        
        /* Layout option styles - updated for a more professional look */
        .layout-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .layout-option {
            position: relative;
            transition: all 0.3s ease;
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .layout-option:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .layout-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .layout-option input[type="radio"]:checked + .layout-content {
            border-color: var(--primary-color);
            background-color: rgba(79, 70, 229, 0.05);
        }

        .layout-option input[type="radio"]:checked + .layout-content .option-check {
            opacity: 1;
            transform: scale(1);
        }

        .layout-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem;
            cursor: pointer;
            border: 2px solid transparent;
            border-radius: 0.5rem;
            height: 100%;
        }

        .layout-visual {
            background-color: #f8fafc;
            width: 100%;
            height: 120px;
            border-radius: 0.5rem;
            margin-bottom: 0.75rem;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .option-check {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            width: 1.5rem;
            height: 1.5rem;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transform: scale(0.5);
            transition: all 0.2s ease;
        }

        .layout-title {
            font-weight: 500;
            color: var(--dark);
            text-align: center;
            font-size: 0.9rem;
        }

        /* Image grid layouts */
        .layout-grid {
            width: 80%;
            height: 80%;
            display: grid;
            gap: 0.25rem;
        }

        .layout-grid-1 {
            grid-template-columns: 1fr;
            grid-template-rows: 1fr;
        }

        .layout-grid-2 {
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr;
        }

        .layout-grid-3 {
            grid-template-columns: 1fr 1fr 1fr;
            grid-template-rows: 1fr;
        }

        .layout-grid-2-2 {
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
        }

        .layout-grid-3-2 {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            width: 80%;
            height: 80%;
        }

        .layout-grid-3-2 .top-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 0.25rem;
            height: 50%;
        }

        .layout-grid-3-2 .bottom-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.25rem;
            height: 50%;
            padding: 0 15%;
        }

        .layout-grid-3-3 {
            grid-template-columns: 1fr 1fr 1fr;
            grid-template-rows: 1fr 1fr 1fr;
        }

        .grid-item {
            background-color: #dbeafe;
            border-radius: 0.25rem;
        }

        .group-image {
            width: 80%;
            height: 70%;
            background-color: #dbeafe;
            border-radius: 0.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .group-caption {
            width: 80%;
            height: 10%;
            margin-top: 0.5rem;
            background-color: #e2e8f0;
            border-radius: 0.25rem;
        }

        
        .layout-preview-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        /* Employee layout representations */
        .layout-employee-grid {
            width: 100%;
            height: 100%;
            display: grid;
            gap: 8px;
            padding: 8px;
        }
        
        .employee-placeholder {
            background-color: #cbd5e1;
            border-radius: 50%;
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 100%; /* Makes it a perfect circle */
        }
        
        .grid-1x1 { grid-template-columns: 1fr; }
        .grid-1x2 { grid-template-columns: repeat(2, 1fr); }
        .grid-1x3 { grid-template-columns: repeat(3, 1fr); }
        .grid-2x2 { grid-template-columns: repeat(2, 1fr); grid-template-rows: repeat(2, 1fr); }
        .grid-3x3 { grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(3, 1fr); }
        
        .grid-3-2 {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .grid-3-2-top {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 8px;
            height: 50%;
        }
        
        .grid-3-2-bottom {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            height: 50%;
            margin: 0 auto;
            width: 66%;
        }

        /* Modal styles for reset confirmation */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            max-width: 500px;
            width: 90%;
            padding: 2rem;
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.show .modal-container {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .modal-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #fee2e2;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .modal-icon i {
            color: #ef4444;
            font-size: 1.5rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
        }

        .modal-body {
            margin-bottom: 1.5rem;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: var(--dark);
        }

        .btn-cancel:hover {
            background: #e2e8f0;
        }

        .btn-confirm {
            background: #ef4444;
            color: white;
        }

        .btn-confirm:hover {
            background: #dc2626;
            box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.3);
        }
    </style>
</head>
<body>
    <div class="app-header">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-3xl font-bold">Email Template Generator</h1>
            <p class="mt-2 opacity-80">Create professional, responsive email templates with ease</p>
        </div>
    </div>

    <div x-data="{ 
        isLoading: false,
        showToast: false,
        toastMessage: '',
        toastType: 'success',
        selectedFile: null,
        currentStep: <?php echo $current_step; ?>,
        themeColor: 'indigo',
        debug: {
            showDebug: false,
            messages: []
        },
        resetModal: {
            show: false
        }
    }" class="main-container">
        <!-- Form Section -->
        <div class="form-section">
            <div class="progress-stepper mb-8">
                <div class="step" :class="{'active': currentStep >= 1, 'completed': currentStep > 1}">
                    <div class="step-circle">
                        <template x-if="currentStep > 1">
                            <i class="fas fa-check"></i>
                        </template>
                        <template x-if="currentStep <= 1">
                            <span>1</span>
                        </template>
                    </div>
                    <div class="step-title">Content</div>
                </div>
                <div class="step" :class="{'active': currentStep >= 2, 'completed': currentStep > 2}">
                    <div class="step-circle">
                        <template x-if="currentStep > 2">
                            <i class="fas fa-check"></i>
                        </template>
                        <template x-if="currentStep <= 2">
                            <span>2</span>
                        </template>
                    </div>
                    <div class="step-title">Images</div>
                </div>
                <div class="step" :class="{'active': currentStep >= 3, 'completed': currentStep > 3}">
                    <div class="step-circle">
                        <template x-if="currentStep > 3">
                            <i class="fas fa-check"></i>
                        </template>
                        <template x-if="currentStep <= 3">
                            <span>3</span>
                        </template>
                    </div>
                    <div class="step-title">Signature</div>
                </div>
                <div class="step" :class="{'active': currentStep >= 4}">
                    <div class="step-circle">
                        <template x-if="currentStep > 4">
                            <i class="fas fa-check"></i>
                        </template>
                        <template x-if="currentStep <= 4">
                            <span>4</span>
                        </template>
                    </div>
                    <div class="step-title">Generate</div>
                </div>
            </div>
            
            <!-- Important: Fixed the enctype attribute for file uploads -->
            <form method="post" enctype="multipart/form-data" @submit="isLoading = true; debug.messages.push('Form submitted')">
                <!-- Header Section -->
                <div class="card slide-up" :class="{'border-indigo-500': currentStep === 1, 'border-green-500': currentStep > 1}">
                    <div class="card-header">
                        <div class="card-header-icon" :class="{'bg-green-500': currentStep > 1}">
                            <i class="fas fa-image"></i>
                        </div>
                        <h2 class="card-title">Header Image</h2>
                    </div>
                    <div class="file-upload">
                        <label class="file-upload-label">
                            <div class="file-upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <span>Drop your header image here or click to browse</span>
                            <p class="text-sm text-gray-500 mt-1">Recommended size: 750px width</p>
                            <input type="file" name="header_image" accept="image/*"
                                @change="selectedFile = $event.target.files[0]?.name; debug.messages.push('Header image selected: ' + selectedFile)">
                        </label>
                        <div x-show="selectedFile" class="selected-file mt-3">
                            <i class="fas fa-file-image"></i>
                            <span x-text="selectedFile"></span>
                        </div>
                    </div>
                </div>

                <!-- Content Section -->
                <div class="card slide-up" style="animation-delay: 0.1s" :class="{'border-indigo-500': currentStep === 1, 'border-green-500': currentStep > 1}">
                    <div class="card-header">
                        <div class="card-header-icon" :class="{'bg-green-500': currentStep > 1}">
                            <i class="fas fa-pen-fancy"></i>
                        </div>
                        <h2 class="card-title">Email Content</h2>
                    </div>
                    <div class="form-group">
                        <textarea id="rich-text-editor" name="body_content" style="height: 300px;"><?php echo isset($_POST['body_content']) ? $_POST['body_content'] : ''; ?></textarea>
                        <p class="text-sm text-gray-500 mt-2">
                            <i class="fas fa-info-circle"></i> 
                            Important dates, titles, and key information will be automatically highlighted
                        </p>
                    </div>
                </div>

                <!-- Layout Section - Redesigned for better professional look -->
                <div class="card slide-up" style="animation-delay: 0.2s" :class="{'border-indigo-500': currentStep === 2, 'border-green-500': currentStep > 2}">
                    <div class="card-header">
                        <div class="card-header-icon" :class="{'bg-green-500': currentStep > 2}">
                            <i class="fas fa-th-large"></i>
                        </div>
                        <h2 class="card-title">Image Layout</h2>
                    </div>
                    
                    <div class="layout-options">
                        <!-- No Images -->
                        <div class="layout-option">
                            <input type="radio" id="layout-none" name="layout_type" value="0" checked 
                                  @change="debug.messages.push('Layout changed to: No Images')">
                            <label for="layout-none" class="layout-content">
                                <div class="layout-visual">
                                    <div class="option-check">
                                        <i class="fas fa-check text-xs"></i>
                                    </div>
                                    <i class="fas fa-ban text-gray-400 text-xl"></i>
                                </div>
                                <div class="layout-title">No Images</div>
                            </label>
                        </div>
                        
                        <!-- Group Photo -->
                        <div class="layout-option">
                            <input type="radio" id="layout-group" name="layout_type" value="group"
                                  @change="debug.messages.push('Layout changed to: Group Photo')">
                            <label for="layout-group" class="layout-content">
                                <div class="layout-visual">
                                    <div class="option-check">
                                        <i class="fas fa-check text-xs"></i>
                                    </div>
                                    <div class="flex flex-col items-center">
                                        <div class="group-image flex items-center justify-center">
                                            <i class="fas fa-users text-blue-400 text-xl"></i>
                                        </div>
                                        <div class="group-caption"></div>
                                    </div>
                                </div>
                                <div class="layout-title">Group Photo</div>
                            </label>
                        </div>
                        
                        <!-- Single Employee -->
                        <div class="layout-option">
                            <input type="radio" id="layout-single" name="layout_type" value="1"
                                  @change="debug.messages.push('Layout changed to: Single Employee')">
                            <label for="layout-single" class="layout-content">
                                <div class="layout-visual">
                                    <div class="option-check">
                                        <i class="fas fa-check text-xs"></i>
                                    </div>
                                    <div class="layout-grid layout-grid-1">
                                        <div class="grid-item flex items-center justify-center">
                                            <i class="fas text-blue-400 text-xl"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="layout-title">Single Employee</div>
                            </label>
                        </div>
                        
                        <!-- Two Employees (1x2) -->
                        <div class="layout-option">
                            <input type="radio" id="layout-two" name="layout_type" value="2"
                                  @change="debug.messages.push('Layout changed to: Two Employees (1x2)')">
                            <label for="layout-two" class="layout-content">
                                <div class="layout-visual">
                                    <div class="option-check">
                                        <i class="fas fa-check text-xs"></i>
                                    </div>
                                    <div class="layout-grid layout-grid-2">
                                        <div class="grid-item"></div>
                                        <div class="grid-item"></div>
                                    </div>
                                </div>
                                <div class="layout-title">Two Employees (1×2)</div>
                            </label>
                        </div>
                        
                        <!-- Three Employees (1x3) -->
                        <div class="layout-option">
                            <input type="radio" id="layout-three" name="layout_type" value="3"
                                  @change="debug.messages.push('Layout changed to: Three Employees (1x3)')">
                            <label for="layout-three" class="layout-content">
                                <div class="layout-visual">
                                    <div class="option-check">
                                        <i class="fas fa-check text-xs"></i>
                                    </div>
                                    <div class="layout-grid layout-grid-3">
                                        <div class="grid-item"></div>
                                        <div class="grid-item"></div>
                                        <div class="grid-item"></div>
                                    </div>
                                </div>
                                <div class="layout-title">Three Employees (1×3)</div>
                            </label>
                        </div>
                        
                        <!-- Four Employees (2x2) -->
                        <div class="layout-option">
                            <input type="radio" id="layout-four" name="layout_type" value="2-2"
                                  @change="debug.messages.push('Layout changed to: Four Employees (2x2)')">
                            <label for="layout-four" class="layout-content">
                                <div class="layout-visual">
                                    <div class="option-check">
                                        <i class="fas fa-check text-xs"></i>
                                    </div>
                                    <div class="layout-grid layout-grid-2-2">
                                        <div class="grid-item"></div>
                                        <div class="grid-item"></div>
                                        <div class="grid-item"></div>
                                        <div class="grid-item"></div>
                                    </div>
                                </div>
                                <div class="layout-title">Four Employees (2×2)</div>
                            </label>
                        </div>
                        
                        <!-- Five Employees (3-2) -->
                        <div class="layout-option">
                            <input type="radio" id="layout-five" name="layout_type" value="3-2"
                                  @change="debug.messages.push('Layout changed to: Five Employees (3-2)')">
                            <label for="layout-five" class="layout-content">
                                <div class="layout-visual">
                                    <div class="option-check">
                                        <i class="fas fa-check text-xs"></i>
                                    </div>
                                    <div class="layout-grid-3-2">
                                        <div class="top-row">
                                            <div class="grid-item"></div>
                                            <div class="grid-item"></div>
                                            <div class="grid-item"></div>
                                        </div>
                                        <div class="bottom-row">
                                            <div class="grid-item"></div>
                                            <div class="grid-item"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="layout-title">Five Employees (3-2)</div>
                            </label>
                        </div>
                        
                        <!-- Nine Employees (3x3) -->
                        <div class="layout-option">
                            <input type="radio" id="layout-nine" name="layout_type" value="3-3"
                                  @change="debug.messages.push('Layout changed to: Nine Employees (3x3)')">
                            <label for="layout-nine" class="layout-content">
                                <div class="layout-visual">
                                    <div class="option-check">
                                        <i class="fas fa-check text-xs"></i>
                                    </div>
                                    <div class="layout-grid layout-grid-3-3">
                                        <div class="grid-item"></div>
                                        <div class="grid-item"></div>
                                        <div class="grid-item"></div>
                                        <div class="grid-item"></div>
                                        <div class="grid-item"></div>
                                        <div class="grid-item"></div>
                                        <div class="grid-item"></div>
                                        <div class="grid-item"></div>
                                        <div class="grid-item"></div>
                                    </div>
                                </div>
                                <div class="layout-title">Nine Employees (3×3)</div>
                            </label>
                        </div>
                    </div>
                    
                    <div id="image-uploads" class="space-y-4 mt-6"></div>
                </div>

                <!-- Signature Section -->
                <div class="card slide-up" style="animation-delay: 0.3s" :class="{'border-indigo-500': currentStep === 3, 'border-green-500': currentStep > 3}">
                    <div class="card-header">
                        <div class="card-header-icon" :class="{'bg-green-500': currentStep > 3}">
                            <i class="fas fa-signature"></i>
                        </div>
                        <h2 class="card-title">Email Signature</h2>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Regards Text</label>
                        <input type="text" 
                            name="regards_text" 
                            placeholder="Best Regards, Sincerely, etc." 
                            class="form-control mb-4"
                            value="<?php echo isset($_POST['regards_text']) ? htmlspecialchars($_POST['regards_text']) : 'Best Regards,'; ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="input-group">
                                <label class="form-label">Signature Name</label>
                                <input type="text" 
                                    name="regards_name" 
                                    placeholder="Your Name" 
                                    class="form-control"
                                    value="<?php echo isset($_POST['regards_name']) ? htmlspecialchars($_POST['regards_name']) : ''; ?>">
                            </div>
                            <div class="input-group">
                                <label class="form-label">Title/Position</label>
                                <input type="text" 
                                    name="regards_title" 
                                    placeholder="Your Title/Position" 
                                    class="form-control"
                                    value="<?php echo isset($_POST['regards_title']) ? htmlspecialchars($_POST['regards_title']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between space-x-4 mt-6">
                    <!-- <button type="button" @click="debug.showDebug = !debug.showDebug" class="btn btn-secondary">
                        <i class="fas fa-bug mr-2"></i>Toggle Debug
                    </button> -->
                    
                    <div>
                        <button type="button" @click="resetModal.show = true" class="btn btn-secondary">
                            <i class="fas fa-redo mr-2"></i>Reset
                        </button>
                        <button type="submit" class="btn btn-primary ml-4" :disabled="isLoading">
                            <template x-if="isLoading">
                                <div class="spinner mr-2"></div>
                            </template>
                            <i class="fas fa-paper-plane mr-2"></i>
                            Generate Template
                        </button>
                    </div>
                </div>
                
                <div x-show="debug.showDebug" class="debug-info mt-4">
                    <h4>Debug Information</h4>
                    <p>Form enctype: <strong id="form-enctype"></strong></p>
                    <p>Selected file: <strong x-text="selectedFile || 'None'"></strong></p>
                    <div>
                        <p>Debug Messages:</p>
                        <ul class="list-disc ml-5">
                            <template x-for="(message, index) in debug.messages" :key="index">
                                <li x-text="message"></li>
                            </template>
                        </ul>
                    </div>
                    
                    <?php if (isset($_FILES) && !empty($_FILES)): ?>
                    <div class="mt-2">
                        <p>Uploaded Files:</p>
                        <pre class="bg-gray-100 p-2 rounded text-xs overflow-auto"><?php print_r($_FILES); ?></pre>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($message) && !empty($message)): ?>
                    <div class="mt-2">
                        <p>Server Message:</p>
                        <p class="<?php echo strpos($message, 'success') !== false ? 'text-green-600' : 'debug-error'; ?>"><?php echo $message; ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Preview Section -->
        <div class="preview-section">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold text-gray-800">Preview</h2>
                <?php if (isset($preview_html) && !empty($preview_html)): ?>
                   <div class="flex flex-wrap gap-2">
                        <!-- <button onclick="copyToClipboard()" class="btn btn-success">
                            <i class="fas fa-copy mr-2"></i>Copy with Formatting
                        </button> -->
                        <button onclick="copyForOutlook()" class="btn btn-primary">
                            <i class="fas fa-envelope mr-2"></i>Copy for Outlook
                        </button>
                        <button onclick="copyForGmail()" class="btn btn-info" style="background: linear-gradient(90deg, #DB4437, #4285F4); color: white;">
                            <i class="fas fa-envelope mr-2"></i>Copy for Gmail
                        </button>
                        <!-- <button onclick="copyAsCleanHtml()" class="btn btn-secondary">
                            <i class="fas fa-code mr-2"></i>Copy as HTML
                        </button> -->
                        <!-- <button onclick="downloadEmailTemplate()" class="btn btn-secondary">
                            <i class="fas fa-download mr-2"></i>Download
                        </button> -->
                        <!-- <button onclick="showManualCopyInstructions()" class="btn btn-secondary" title="What to do if copying doesn't work">
                            <i class="fas fa-question-circle"></i>
                        </button> -->
                    </div>
                <?php endif; ?>
            </div>

            <?php if (isset($preview_html) && !empty($preview_html)): ?>
                <div id="formatted-content">
                    <?php echo $preview_html; ?>
                </div>
            <?php else: ?>
                <div class="preview-empty">
                    <i class="fas fa-envelope-open-text"></i>
                    <p>Your email preview will appear here</p>
                    <p>Generate a template to see how your email will look</p>
                </div>
            <?php endif; ?>
            
            <!-- Manual Copy Instructions (initially hidden) -->
            <div id="manual-copy-instructions" class="hidden mt-4 p-6 bg-blue-50 border border-blue-200 rounded-lg">
    <h3 class="text-lg font-semibold text-blue-700 mb-3">Manually Copy Formatted Content</h3>
    
    <div class="mb-4">
        <h4 class="font-medium text-blue-800 mb-2">Method 1: Copy from Preview</h4>
        <ol class="list-decimal pl-5 space-y-2">
            <li>Click anywhere in the preview area above</li>
            <li>Press <kbd class="px-2 py-1 bg-gray-200 rounded text-sm">Ctrl+A</kbd> (or <kbd class="px-2 py-1 bg-gray-200 rounded text-sm">⌘+A</kbd> on Mac) to select all content</li>
            <li>Press <kbd class="px-2 py-1 bg-gray-200 rounded text-sm">Ctrl+C</kbd> (or <kbd class="px-2 py-1 bg-gray-200 rounded text-sm">⌘+C</kbd> on Mac) to copy</li>
            <li>Open your email application and paste using <kbd class="px-2 py-1 bg-gray-200 rounded text-sm">Ctrl+V</kbd> (or <kbd class="px-2 py-1 bg-gray-200 rounded text-sm">⌘+V</kbd> on Mac)</li>
        </ol>
    </div>
    
    <div class="mb-4">
        <h4 class="font-medium text-blue-800 mb-2">Method 2: For Gmail</h4>
        <ol class="list-decimal pl-5 space-y-2">
            <li>Click the "Copy for Gmail" button above</li>
            <li>Open Gmail and create a new email</li>
            <li>Right-click in the email body and select "Paste" or use <kbd class="px-2 py-1 bg-gray-200 rounded text-sm">Ctrl+V</kbd></li>
            <li>If formatting doesn't appear correctly, try using the "Paste without formatting" option first (<kbd class="px-2 py-1 bg-gray-200 rounded text-sm">Ctrl+Shift+V</kbd>), then use the "Copy as HTML" button and paste again</li>
        </ol>
    </div>
    
    <div class="mb-4">
        <h4 class="font-medium text-blue-800 mb-2">Method 3: For Microsoft Outlook</h4>
        <ol class="list-decimal pl-5 space-y-2">
            <li>Click the "Copy for Outlook" button above</li>
            <li>Open Outlook and create a new email</li>
            <li>Right-click in the email body and select "Paste" or use <kbd class="px-2 py-1 bg-gray-200 rounded text-sm">Ctrl+V</kbd></li>
            <li>If given paste options, choose "Keep Source Formatting"</li>
        </ol>
    </div>
    
    <div class="mb-4">
        <h4 class="font-medium text-blue-800 mb-2">Method 4: Download and Import</h4>
        <ol class="list-decimal pl-5 space-y-2">
            <li>Click the "Download" button to save the HTML file</li>
            <li>For Gmail, you can open the HTML file in a browser, select all content (Ctrl+A), copy (Ctrl+C), then paste into Gmail</li>
            <li>For Outlook, you can use File > Open & Export > Open from File and select the downloaded HTML file</li>
        </ol>
    </div>
    
    <div class="mt-4 p-3 bg-yellow-50 border-l-4 border-yellow-400 text-sm">
        <p class="text-yellow-800">
            <i class="fas fa-lightbulb mr-2"></i>
            <strong>Gmail Tip:</strong> Gmail has stricter rules about what HTML it accepts. If your formatting isn't preserved when pasting, try clicking the "Copy for Gmail" button which optimizes the content specifically for Gmail's requirements.
        </p>
    </div>
    
    <button onclick="document.getElementById('manual-copy-instructions').classList.add('hidden')" 
            class="mt-4 text-blue-700 hover:text-blue-900 flex items-center">
        <i class="fas fa-times mr-2"></i> Close
    </button>
</div>
        </div>

        <!-- Reset Confirmation Modal -->
        <div class="modal-overlay" :class="{'show': resetModal.show}" @click.self="resetModal.show = false">
            <div class="modal-container">
                <div class="modal-header">
                    <div class="modal-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 class="modal-title">Reset Form?</h3>
                </div>
                <div class="modal-body">
                    <p class="text-gray-600">
                        Are you sure you want to reset the form? This will clear all your inputs and any changes you've made will be lost.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" @click="resetModal.show = false">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-confirm" @click="performReset()">
                        <i class="fas fa-redo mr-2"></i>Reset Form
                    </button>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <div x-show="showToast" 
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-x-full"
            x-transition:enter-end="opacity-100 transform translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform translate-x-0"
            x-transition:leave-end="opacity-0 transform translate-x-full"
            class="toast"
            :class="{ 'bg-green-100': toastType === 'success', 'bg-red-100': toastType === 'error' }">
            <i class="fas" :class="{ 'fa-check-circle text-green-500': toastType === 'success', 'fa-times-circle text-red-500': toastType === 'error' }"></i>
            <span x-text="toastMessage"></span>
        </div>
    </div>

    <script>
        /**
         * Updates the image upload fields based on the selected layout type
         * @param {string} layoutType - The selected layout type
         */
        function updateImageUploadFields(layoutType) {
            const container = document.getElementById('image-uploads');
            container.innerHTML = '';

            if (layoutType === 'group') {
                // Create a group photo upload field
                const div = document.createElement('div');
                div.className = 'card p-6 fade-in';
                div.innerHTML = `
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800">Group Photo</h3>
                        <div class="file-upload">
                            <label class="file-upload-label">
                                <div class="file-upload-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <span>Drop your group photo here or click to browse</span>
                                <input type="file" name="group_image" accept="image/*"
                                    onchange="updateFileName(this)">
                            </label>
                            <div class="selected-file" style="display: none;"></div>
                        </div>
                        <div class="input-group">
                            <label class="form-label">Caption (optional)</label>
                            <input type="text" name="group_caption" placeholder="Group photo caption" class="form-control">
                        </div>
                    </div>
                `;
                container.appendChild(div);
                return;
            }

            let numImages = 0;
            switch(layoutType) {
                case '1': numImages = 1; break;
                case '2': numImages = 2; break;
                case '3': numImages = 3; break;
                case '2-2': numImages = 4; break;
                case '3-2': numImages = 5; break;
                case '3-3': numImages = 9; break;
            }

            for(let i = 1; i <= numImages; i++) {
                const div = document.createElement('div');
                div.className = 'card p-6 fade-in';
                div.style.animationDelay = `${(i-1) * 0.1}s`;
                div.innerHTML = `
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800">Employee ${i}</h3>
                        <div class="file-upload">
                            <label class="file-upload-label">
                                <div class="file-upload-icon">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                                <span>Drop employee image here or click to browse</span>
                                <input type="file" name="employee_image_${i}" accept="image/*"
                                    onchange="updateFileName(this)">
                            </label>
                            <div class="selected-file" style="display: none;"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="input-group">
                                <label class="form-label">Name</label>
                                <input type="text" name="employee_name_${i}" placeholder="Employee Name" class="form-control">
                            </div>
                            <div class="input-group">
                                <label class="form-label">Title</label>
                                <input type="text" name="employee_title_${i}" placeholder="Employee Title" class="form-control">
                            </div>
                        </div>
                    </div>
                `;
                container.appendChild(div);
            }
        }

        function updateFileName(input) {
            const fileNameDisplay = input.parentElement.nextElementSibling;
            if (input.files.length > 0) {
                fileNameDisplay.innerHTML = `<i class="fas fa-file-image"></i> ${input.files[0].name}`;
                fileNameDisplay.style.display = 'block';
                
                // Add preview
                const parentCard = input.closest('.card');
                let previewContainer = parentCard.querySelector('.image-preview');
                if (!previewContainer) {
                    previewContainer = document.createElement('div');
                    previewContainer.className = 'image-preview mt-4';
                    input.parentElement.parentElement.appendChild(previewContainer);
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewContainer.innerHTML = `
                        <img src="${e.target.result}" class="rounded-lg shadow-md" id="preview-${input.name}">
                        <button type="button" class="btn btn-error mt-2" onclick="removeImage(this, '${input.name}')">
                            <i class="fas fa-trash-alt mr-2"></i>Remove
                        </button>
                    `;
                };
                reader.readAsDataURL(input.files[0]);
                
                // Update debug info if available
                const debug = document.querySelector('[x-data]').__x.$data.debug;
                if (debug) {
                    debug.messages.push(`File selected for ${input.name}: ${input.files[0].name}`);
                }
            } else {
                fileNameDisplay.style.display = 'none';
            }
        }

        function removeImage(button, inputName) {
            const previewContainer = button.parentElement;
            const parentCard = previewContainer.closest('.card');
            const fileUpload = parentCard.querySelector(`input[name="${inputName}"]`);
            
            // Create a new file input to replace the current one
            const newFileInput = document.createElement('input');
            newFileInput.type = 'file';
            newFileInput.name = inputName;
            newFileInput.accept = 'image/*';
            newFileInput.setAttribute('onchange', 'updateFileName(this)');
            
            // Replace the old file input with the new one
            fileUpload.parentNode.replaceChild(newFileInput, fileUpload);
            
            // Remove the preview and hide the filename
            previewContainer.remove();
            
            // Hide the filename display
            const fileNameDisplay = parentCard.querySelector('.selected-file');
            if (fileNameDisplay) {
                fileNameDisplay.style.display = 'none';
            }
            
            // Update debug info if available
            const debug = document.querySelector('[x-data]').__x.$data.debug;
            if (debug) {
                debug.messages.push(`File removed from ${inputName}`);
            }
            
            // Show toast notification
            showToast('Image removed successfully', 'success');
        }

        function showToast(message, type = 'success', duration = 3000) {
            const toast = document.querySelector('[x-data]').__x.$data;
            toast.toastMessage = message;
            toast.toastType = type;
            toast.showToast = true;
            
            setTimeout(() => {
                toast.showToast = false;
            }, duration);
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Make sure the form has the proper enctype
            const form = document.querySelector('form');
            if (form) {
                // Display form enctype in debug
                const formEnctypeElement = document.getElementById('form-enctype');
                if (formEnctypeElement) {
                    formEnctypeElement.textContent = form.getAttribute('enctype') || 'Not set';
                }
                
                // Ensure correct enctype
                if (form.getAttribute('enctype') !== 'multipart/form-data') {
                    form.setAttribute('enctype', 'multipart/form-data');
                    console.log('Added missing enctype attribute to form');
                    
                    // Update debug info
                    const debug = document.querySelector('[x-data]').__x.$data.debug;
                    if (debug) {
                        debug.messages.push('Added missing enctype="multipart/form-data" to form');
                    }
                }
            }
            
            // Add event listeners to layout type radio buttons
            document.querySelectorAll('input[name="layout_type"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    updateImageUploadFields(this.value);
                });
            });

            // Initialize the TinyMCE editor
            tinymce.init({
    selector: '#rich-text-editor',
    height: 300,
    plugins: 'code fontsize paste link image autolink lists table hr charmap preview',
    toolbar: 'undo redo | formatselect | fontsizeselect lineheight | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | table link image hr | code',
    menubar: false,
    statusbar: true,
    fontsize_formats: "8pt 9pt 10pt 11pt 12pt 14pt 16pt 18pt 24pt 36pt",
    lineheight_formats: "1 1.2 1.5 2.0 2.5 3.0",
    content_style: `
        body { 
            font-family: 'Proxima Nova', Arial, sans-serif !important; 
        } 
        p, div, h1, h2, h3, h4, h5, h6, td, li { 
            font-family: 'Proxima Nova', Arial, sans-serif !important;
            line-height: inherit !important;
        }
        .line-height-1 { line-height: 1 !important; }
        .line-height-1-2 { line-height: 1.2 !important; }
        .line-height-1-5 { line-height: 1.5 !important; }
        .line-height-2 { line-height: 2 !important; }
        .line-height-2-5 { line-height: 2.5 !important; }
        .line-height-3 { line-height: 3 !important; }
    `,
    setup: function(editor) {
        // Create a custom line height dropdown
        editor.ui.registry.addMenuButton('lineheight', {
            icon: 'line-height',
            tooltip: 'Line Height',
            fetch: function(callback) {
                const items = [
                    {
                        type: 'menuitem',
                        text: '1.0',
                        onAction: function() {
                            applyLineHeight(editor, '1.0');
                        }
                    },
                    {
                        type: 'menuitem',
                        text: '1.2',
                        onAction: function() {
                            applyLineHeight(editor, '1.2');
                        }
                    },
                    {
                        type: 'menuitem',
                        text: '1.5',
                        onAction: function() {
                            applyLineHeight(editor, '1.5');
                        }
                    },
                    {
                        type: 'menuitem',
                        text: '2.0',
                        onAction: function() {
                            applyLineHeight(editor, '2.0');
                        }
                    },
                    {
                        type: 'menuitem',
                        text: '2.5',
                        onAction: function() {
                            applyLineHeight(editor, '2.5');
                        }
                    },
                    {
                        type: 'menuitem',
                        text: '3.0',
                        onAction: function() {
                            applyLineHeight(editor, '3.0');
                        }
                    }
                ];
                callback(items);
            }
        });
        
        // Function to apply line height with Outlook compatibility
        function applyLineHeight(editor, value) {
            editor.execCommand('LineHeight', false, value);
            
            // Add Outlook-specific attributes
            const node = editor.selection.getNode();
            const ptValue = parseFloat(value) * 16;
            
            editor.dom.setAttribs(node, {
                'style': `line-height: ${value} !important; mso-line-height-rule: exactly; mso-line-height-alt: ${ptValue}pt;` + 
                         editor.dom.getAttrib(node, 'style'),
                'class': `line-height-${value.replace('.', '-')} ` + editor.dom.getAttrib(node, 'class')
            });
            
            // If it's a paragraph, also add MsoNormal class for Outlook
            if (node.nodeName === 'P') {
                editor.dom.addClass(node, 'MsoNormal');
            }
        }
        
        // Add alignment buttons if they're not already available
        if (!editor.ui.registry.getAll().buttons.alignleft) {
            editor.ui.registry.addButton('alignleft', {
                icon: 'align-left',
                tooltip: 'Align Left',
                onAction: function() {
                    editor.execCommand('JustifyLeft');
                }
            });
            
            editor.ui.registry.addButton('aligncenter', {
                icon: 'align-center',
                tooltip: 'Align Center',
                onAction: function() {
                    editor.execCommand('JustifyCenter');
                }
            });
            
            editor.ui.registry.addButton('alignright', {
                icon: 'align-right',
                tooltip: 'Align Right',
                onAction: function() {
                    editor.execCommand('JustifyRight');
                }
            });
        }
        
        // Add font size adjustment buttons
        editor.ui.registry.addMenuButton('fontsizeselect', {
            icon: 'resize',
            tooltip: 'Font Size',
            fetch: function(callback) {
                const fontSizes = [
                    {text: '8pt', size: '8pt'},
                    {text: '9pt', size: '9pt'},
                    {text: '10pt', size: '10pt'},
                    {text: '11pt', size: '11pt'},
                    {text: '12pt', size: '12pt'},
                    {text: '14pt', size: '14pt'},
                    {text: '16pt', size: '16pt'},
                    {text: '18pt', size: '18pt'},
                    {text: '24pt', size: '24pt'},
                    {text: '36pt', size: '36pt'}
                ];
                
                const items = fontSizes.map(function(item) {
                    return {
                        type: 'menuitem',
                        text: item.text,
                        onAction: function() {
                            editor.execCommand('FontSize', false, item.size);
                        }
                    };
                });
                
                callback(items);
            }
        });
        
        // Apply default formatting on init
        editor.on('init', function() {
            editor.getBody().style.fontFamily = "'Proxima Nova', Arial, sans-serif";
            setTimeout(function() {
                editor.execCommand('FontSize', false, '12pt');
                applyLineHeight(editor, '1.5');
            }, 500);
        });
    }
});

 // Initialize any existing layout
 if (document.querySelector('input[name="layout_type"]:checked')) {
                updateImageUploadFields(document.querySelector('input[name="layout_type"]:checked').value);
            }
            
            // Debug info
            console.log('DOM fully loaded');
            const debug = document.querySelector('[x-data]').__x.$data.debug;
            if (debug) {
                debug.messages.push('DOM fully loaded and ready');
            }
            
            // Add special handling for file inputs to ensure proper file data transmission
            document.querySelectorAll('input[type="file"]').forEach(input => {
                input.addEventListener('change', function() {
                    // Log file selection
                    console.log(`File selected for ${this.name}:`, this.files[0] ? this.files[0].name : 'None');
                    
                    // Special handling for header image
                    if (this.name === 'header_image') {
                        const headerImage = this.files[0];
                        if (headerImage) {
                            // Update debug info
                            if (debug) {
                                debug.messages.push(`Header image selected: ${headerImage.name} (${headerImage.size} bytes)`);
                            }
                            
                            // Update the visual display
                            updateFileName(this);
                        }
                    }
                });
            });

            // Add Alpine.js method to handle form reset
            if (document.querySelector('[x-data]').__x) {
                document.querySelector('[x-data]').__x.$data.performReset = function() {
                    try {
                        // Close the reset modal
                        this.resetModal.show = false;
                        
                        // Add debug message
                        if (this.debug) {
                            this.debug.messages.push('Form reset initiated through modal');
                        }
                        
                        // Reset TinyMCE editor content
                        if (typeof tinymce !== 'undefined' && tinymce.get('rich-text-editor')) {
                            tinymce.get('rich-text-editor').setContent('');
                        }
                        
                        // Clear file input fields
                        document.querySelectorAll('input[type="file"]').forEach(fileInput => {
                            fileInput.value = '';
                        });
                        
                        // Hide any selected file displays
                        document.querySelectorAll('.selected-file').forEach(element => {
                            element.style.display = 'none';
                        });
                        
                        // Remove any image previews
                        document.querySelectorAll('.image-preview').forEach(element => {
                            element.remove();
                        });
                        
                        // Reset radio buttons for layout to default (No Images)
                        const defaultLayout = document.getElementById('layout-none');
                        if (defaultLayout) {
                            defaultLayout.checked = true;
                            // Trigger the change event to update the form
                            const event = new Event('change');
                            defaultLayout.dispatchEvent(event);
                        }
                        
                        // Reset text inputs
                        document.querySelectorAll('input[type="text"]').forEach(input => {
                            input.value = '';
                        });
                        
                        // Reset the signature fields to defaults
                        const regardsText = document.querySelector('input[name="regards_text"]');
                        if (regardsText) {
                            regardsText.value = 'Best Regards,';
                        }
                        
                        // Clear the preview section
                        const previewContent = document.getElementById('formatted-content');
                        if (previewContent) {
                            const previewSection = previewContent.parentElement;
                            
                            // Replace the preview content with the empty state
                            previewSection.innerHTML = `
                            <div class="flex justify-between items-center mb-6">
                                <h2 class="text-2xl font-semibold text-gray-800">Preview</h2>
                            </div>
                            <div class="preview-empty">
                                <i class="fas fa-envelope-open-text"></i>
                                <p>Your email preview will appear here</p>
                                <p>Generate a template to see how your email will look</p>
                            </div>`;
                        }
                        
                        // Reset Alpine.js state
                        this.currentStep = 1;
                        this.selectedFile = null;
                        
                        // Show success toast
                        this.toastMessage = 'Form has been reset successfully';
                        this.toastType = 'success';
                        this.showToast = true;
                        setTimeout(() => { this.showToast = false; }, 3000);
                        
                        // Reload the page after a short delay to ensure a complete reset
                        setTimeout(function() {
                            window.location.href = window.location.pathname; // Reload without parameters
                        }, 1000);
                    } catch (err) {
                        console.error('Error during form reset:', err);
                        
                        // Show error toast
                        this.toastMessage = 'An error occurred while resetting the form';
                        this.toastType = 'error';
                        this.showToast = true;
                        setTimeout(() => { this.showToast = false; }, 3000);
                    }
                };
            }
        });
        
        // Functions for copying and downloading the template
        function copyToClipboard() {
            const content = document.getElementById('formatted-content');
            if (!content) {
                showToast('Nothing to copy', 'error');
                return;
            }
            
            // Try multiple clipboard methods for best compatibility
            copyWithFormattingPreserved(content)
                .then(result => {
                    if (result.success) {
                        showToast(result.message, 'success');
                    } else {
                        showManualCopyInstructions();
                    }
                })
                .catch(error => {
                    console.error('Copy failed:', error);
                    showManualCopyInstructions();
                });
        }
        
        /**
         * Attempt to copy with formatting preserved using multiple methods
         * @param {HTMLElement} element - The element to copy
         * @return {Promise<{success: boolean, message: string}>} - Result of the copy operation
         */
        async function copyWithFormattingPreserved(element) {
            // Method 1: Try Clipboard API with HTML format
            if (navigator.clipboard && typeof navigator.clipboard.write === 'function') {
                try {
                    // Create ClipboardItem with both HTML and text formats
                    const htmlBlob = new Blob([element.outerHTML], { type: 'text/html' });
                    const textBlob = new Blob([element.innerText], { type: 'text/plain' });
                    
                    const clipboardItem = new ClipboardItem({
                        'text/html': htmlBlob,
                        'text/plain': textBlob
                    });
                    
                    await navigator.clipboard.write([clipboardItem]);
                    return { success: true, message: 'Content copied with formatting preserved!' };
                } catch (err) {
                    console.warn('Clipboard API write failed, trying alternative method', err);
                }
            }
            
            // Method 2: Create a frame with document.execCommand
            try {
                // Create invisible iframe
                const iframe = document.createElement('iframe');
                iframe.style.position = 'fixed';
                iframe.style.top = '-9999px';
                iframe.style.left = '-9999px';
                iframe.style.width = '100px';
                iframe.style.height = '100px';
                iframe.setAttribute('tabindex', '-1');
                document.body.appendChild(iframe);
                
                // Write content to iframe
                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                iframeDoc.open();
                iframeDoc.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            body, p, div, span, table, td, strong, em, h1, h2, h3, h4, h5, h6, a, li, blockquote {
                                font-family: "Proxima Nova", Arial, sans-serif !important;
                                line-height: 1.5 !important;
                            }
                            * {
                                font-family: "Proxima Nova", Arial, sans-serif !important;
                            }
                        </style>
                    </head>
                    <body>${element.outerHTML}</body>
                    </html>
                `);
                iframeDoc.close();
                
                // Focus and select all content
                iframe.contentWindow.focus();
                iframeDoc.execCommand('selectAll', false, null);
                
                // Copy the selection
                const success = iframeDoc.execCommand('copy');
                
                // Clean up
                document.body.removeChild(iframe);
                
                if (success) {
                    return { success: true, message: 'Content copied with formatting preserved!' };
                }
            } catch (err) {
                console.warn('iframe method failed', err);
            }
            
            // Method 3: Create a selection in the current document
            try {
                // Clone the node to avoid modifying the original
                const clone = element.cloneNode(true);
                
                // Create a div to hold the cloned content
                const container = document.createElement('div');
                container.style.position = 'fixed';
                container.style.left = '-9999px';
                container.style.top = '0';
                container.appendChild(clone);
                document.body.appendChild(container);
                
                // Create a selection range
                const range = document.createRange();
                range.selectNodeContents(container);
                
                // Apply the selection
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
                
                // Attempt the copy
                const success = document.execCommand('copy');
                
                // Clean up
                selection.removeAllRanges();
                document.body.removeChild(container);
                
                if (success) {
                    return { success: true, message: 'Content copied with formatting preserved!' };
                }
            } catch (err) {
                console.warn('Selection method failed', err);
            }
            
            // Method 4: Try creating a temporary contentEditable element
            try {
                const tempDiv = document.createElement('div');
                tempDiv.contentEditable = 'true';
                tempDiv.style.position = 'fixed';
                tempDiv.style.left = '-9999px';
                tempDiv.style.top = '0';
                tempDiv.innerHTML = element.outerHTML;
                document.body.appendChild(tempDiv);
                
                // Select the content
                const range = document.createRange();
                range.selectNodeContents(tempDiv);
                
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
                
                // Copy
                const success = document.execCommand('copy');
                
                // Clean up
                selection.removeAllRanges();
                document.body.removeChild(tempDiv);
                
                if (success) {
                    return { success: true, message: 'Content copied with formatting!' };
                }
            } catch (err) {
                console.warn('ContentEditable method failed', err);
            }
            
            // If all methods fail, fallback to plain text
            try {
                const text = element.innerText || element.textContent;
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-9999px';
                textArea.style.top = '0';
                document.body.appendChild(textArea);
                
                textArea.select();
                const success = document.execCommand('copy');
                document.body.removeChild(textArea);
                
                if (success) {
                    return { success: true, message: 'Content copied as plain text (formatting not preserved)' };
                }
            } catch (err) {
                console.warn('Plain text fallback failed', err);
            }
            
            return { success: false, message: 'Unable to copy content' };
        }
        
        function downloadEmailTemplate() {
            const content = document.getElementById('formatted-content');
            if (!content) {
                showToast('Nothing to download', 'error');
                return;
            }
            
            // Create a complete HTML document
            const htmlDocument = `<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Email Template</title>
</head>
<body>
    ${content.outerHTML}
</body>
</html>`;
            
            // Create blob and download link
            const blob = new Blob([htmlDocument], { type: 'text/html' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'email-template.html';
            
            // Trigger download
            document.body.appendChild(a);
            a.click();
            
            // Clean up
            setTimeout(() => {
                document.body.removeChild(a);
                URL.revokeObjectURL(a.href);
            }, 100);
            
            showToast('Email template downloaded successfully', 'success');
        }
        
        function showManualCopyInstructions() {
            const instructions = document.getElementById('manual-copy-instructions');
            if (instructions) {
                instructions.classList.remove('hidden');
            }
            
            // Highlight the content area to make it more obvious
            const content = document.getElementById('formatted-content');
            if (content) {
                const originalBorder = content.style.border;
                const originalBackground = content.style.background;
                
                content.style.border = '2px dashed #4f46e5';
                content.style.background = 'rgba(79, 70, 229, 0.05)';
                content.style.padding = '10px';
                
                // Temporarily make the content selectable if it wasn't already
                const originalUserSelect = content.style.userSelect;
                content.style.userSelect = 'all';
                content.style.webkitUserSelect = 'all';
                content.style.msUserSelect = 'all';
                
                // Reset after 5 seconds
                setTimeout(() => {
                    content.style.border = originalBorder;
                    content.style.background = originalBackground;
                    content.style.userSelect = originalUserSelect;
                    content.style.webkitUserSelect = originalUserSelect;
                    content.style.msUserSelect = originalUserSelect;
                }, 5000);
            }
        }

        function copyForOutlook() {
            const content = document.getElementById('formatted-content');
            if (!content) {
                showToast('Nothing to copy', 'error');
                return;
            }
            
            // Create a special Outlook-optimized version
            const outlookElement = document.createElement('div');
            
            // Clone content and process all elements to enforce font and line spacing
            const contentClone = content.cloneNode(true);
            
            // First - find all paragraphs and add a spacing system for Outlook
            const paragraphs = contentClone.querySelectorAll('p');
            paragraphs.forEach((p, index) => {
                // First extract any line-height value from the paragraph
                const style = p.getAttribute('style') || '';
                let lineHeightMatch = style.match(/line-height\s*:\s*([^;!]+)(!important)?/i);
                let lineHeightValue = lineHeightMatch ? parseFloat(lineHeightMatch[1]) : 1.5;
                
                // Set a consistent Outlook-compatible style
                let newStyles = [];
                
                // Preserve non-line-height styles
                style.split(';').forEach(rule => {
                    const trimmedRule = rule.trim();
                    if (trimmedRule && !trimmedRule.includes('line-height')) {
                        newStyles.push(trimmedRule);
                    }
                });
                
                // Add Outlook-specific styling - critical for proper line spacing
                newStyles.push(`font-family:"Proxima Nova RG", "Proxima Nova", Arial, sans-serif !important`);
                newStyles.push(`line-height:${lineHeightValue} !important`);
                newStyles.push(`mso-line-height-rule:exactly`);
                newStyles.push(`mso-line-height-alt:${lineHeightValue * 16}pt`);
                
                // Setup margins for consistent spacing - required for Outlook
                newStyles.push('margin-top:0');
                newStyles.push('margin-bottom:12pt');
                
                p.setAttribute('style', newStyles.join(';') + ';');
                
                // Add Outlook-specific class
                let className = p.getAttribute('class') || '';
                if (lineHeightValue <= 1.2) {
                    className += ' line-height-10 MsoNormal';
                } else if (lineHeightValue <= 1.8) {
                    className += ' line-height-15 MsoNormal';
                } else {
                    className += ' line-height-20 MsoNormal';
                }
                p.setAttribute('class', className.trim());
            });
            
            // Second - add special spacing divs between paragraphs for Outlook
            const contentBox = contentClone.querySelector('td.mceContentBody, td.outlook-content-cell');
            if (contentBox) {
                // Create a wrapper to hold the new content with spacers
                const wrapper = document.createElement('div');
                const children = Array.from(contentBox.childNodes);
                
                children.forEach(child => {
                    // Append the original element
                    wrapper.appendChild(child.cloneNode(true));
                    
                    // Add spacing divs between block elements
                    if (child.nodeType === 1 && ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table'].includes(child.tagName.toLowerCase())) {
                        const spacer = document.createElement('div');
                        spacer.className = 'spacer-10'; 
                        spacer.innerHTML = '&nbsp;';
                        spacer.style.lineHeight = '10px';
                        spacer.style.fontSize = '10px';
                        spacer.style.msoLineHeightRule = 'exactly';
                        wrapper.appendChild(spacer);
                    }
                });
                
                // Replace content box contents
                contentBox.innerHTML = '';
                Array.from(wrapper.childNodes).forEach(node => {
                    contentBox.appendChild(node);
                });
            }
            
            // Process all remaining elements to enforce Proxima Nova font and line spacing
            const allElements = contentClone.querySelectorAll('*');
            allElements.forEach(el => {
                if (el.nodeType !== 1) return; // Skip non-element nodes
                
                // Get existing style
                let style = el.getAttribute('style') || '';
                
                // Replace existing font-family to avoid conflicts
                style = style.replace(/font-family\s*:[^;]+;/gi, '');
                style += ';font-family:"Proxima Nova RG", "Proxima Nova", Arial, sans-serif !important;';
                
                // Special handling for text elements that aren't paragraphs
                if (['div', 'span', 'strong', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'td', 'th', 'a', 'li'].includes(el.tagName.toLowerCase()) && el.tagName.toLowerCase() !== 'p') {
                    // Check if element has line-height
                    const lineHeightMatch = style.match(/line-height\s*:\s*([^;!]+)(!important)?/i);
                    if (lineHeightMatch) {
                        const lineHeightValue = parseFloat(lineHeightMatch[1]);
                        
                        // Convert to pt for Outlook if it's a number
                        const ptValue = lineHeightValue * 16;
                        
                        // Remove existing line-height
                        style = style.replace(/line-height\s*:[^;]+;/gi, '');
                        
                        // Add with MSO properties
                        style += `;line-height:${lineHeightValue} !important;`;
                        style += `;mso-line-height-rule:exactly;mso-line-height-alt:${ptValue}pt;`;
                    }
                }
                
                el.setAttribute('style', style);
            });
            
            // Create a complete document with Outlook conditional comments
            outlookElement.innerHTML = `
                <!DOCTYPE html>
                <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word">
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="ProgId" content="Word.Document">
                    <meta name="Generator" content="Microsoft Word 15">
                    <meta name="Originator" content="Microsoft Word 15">
                    <title>Email Content</title>
                    <!--[if mso]>
                    <style>
                    /* Outlook-specific font and line spacing rules */
                    body, table, td, a { 
                        font-family:"Proxima Nova RG", "Proxima Nova", Arial, sans-serif !important;
                    }
                    p, h1, h2, h3, h4, h5, h6, strong, em, span, div { 
                        font-family:"Proxima Nova RG", "Proxima Nova", Arial, sans-serif !important;
                        mso-line-height-rule: exactly;
                    }
                    /* Outlook needs explicit line heights and margins on paragraphs */
                    p {
                        margin-top:0pt !important;
                        margin-bottom:12pt !important; 
                        mso-margin-top-alt:0pt !important;
                        mso-margin-bottom-alt:12pt !important;
                    }
                    /* MsoNormal style is critical for Outlook */
                    p.MsoNormal, li.MsoNormal, div.MsoNormal {
                        margin:0pt;
                        margin-bottom:12pt;
                        line-height:115%;
                        font-size:11.0pt;
                        font-family:"Proxima Nova",sans-serif;
                        mso-fareast-language:EN-US;
                    }
                    /* Handle specific line heights from TinyMCE */
                    .line-height-10 { line-height:1.0 !important; mso-line-height-rule:exactly; mso-line-height-alt:16pt; }
                    .line-height-15 { line-height:1.5 !important; mso-line-height-rule:exactly; mso-line-height-alt:24pt; }
                    .line-height-20 { line-height:2.0 !important; mso-line-height-rule:exactly; mso-line-height-alt:32pt; }
                    
                    /* Spacing helper classes */
                    .spacer-10 { line-height:10pt !important; font-size:10pt !important; mso-line-height-rule:exactly; }
                    
                    /* Table spacing control */
                    table { mso-table-lspace:0pt; mso-table-rspace:0pt; }
                    </style>
                    <xml>
                    <o:OfficeDocumentSettings>
                        <o:AllowPNG/>
                        <o:PixelsPerInch>96</o:PixelsPerInch>
                    </o:OfficeDocumentSettings>
                    </xml>
                    <![endif]-->
                    <style>
                        body { margin: 0; padding: 0; font-family: "Proxima Nova", Arial, sans-serif; }
                        p { margin-top: 0; margin-bottom: 12pt; }
                        .spacer-10 { line-height: 10px; font-size: 10px; }
                    </style>
                </head>
                <body style="margin:0;padding:0;font-family:'Proxima Nova RG', 'Proxima Nova', Arial, sans-serif;">
                    <!--[if mso]>
                    <table width="750" cellspacing="0" cellpadding="0" border="0" align="center">
                    <tr><td style="font-family:'Proxima Nova RG', 'Proxima Nova', Arial, sans-serif !important;mso-line-height-rule:exactly;">
                    <![endif]-->
                    ${contentClone.outerHTML}
                    <!--[if mso]>
                    </td></tr>
                    </table>
                    <![endif]-->
                </body>
                </html>
            `;
            
            // Try to copy with strong formatting
            copyWithFormattingPreserved(outlookElement)
                .then(result => {
                    if (result.success) {
                        showToast('Content copied with enhanced line spacing for Outlook!', 'success');
                    } else {
                        // Try an alternative method specifically for Outlook
                        const success = copyWithOutlookFormat(outlookElement);
                        if (success) {
                            showToast('Content copied with special Outlook formatting!', 'success');
                        } else {
                            showManualCopyInstructions();
                        }
                    }
                })
                .catch(error => {
                    console.error('Outlook-specific copy failed:', error);
                    showManualCopyInstructions();
                });
        }

        function copyWithOutlookFormat(element) {
            try {
                // Create a temporary iframe for Outlook-friendly copying
                const iframe = document.createElement('iframe');
                iframe.style.position = 'fixed';
                iframe.style.top = '-9999px';
                iframe.style.left = '-9999px';
                iframe.width = '750px';
                iframe.height = '500px';
                document.body.appendChild(iframe);
                
                // Use Word's preferred HTML structure with explicit namespace declarations
                const doc = iframe.contentDocument || iframe.contentWindow.document;
                doc.open();
                doc.write(`
                    <html xmlns:o="urn:schemas-microsoft-com:office:office"
                          xmlns:w="urn:schemas-microsoft-com:office:word"
                          xmlns:m="http://schemas.microsoft.com/office/2004/12/omml"
                          xmlns="http://www.w3.org/TR/REC-html40">
                    <head>
                        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
                        <meta name="ProgId" content="Word.Document">
                        <meta name="Generator" content="Microsoft Word 15">
                        <meta name="Originator" content="Microsoft Word 15">
                        <!--[if mso]>
                        <style>
                        /* These styles are critical for Outlook */
                        p.MsoNormal, li.MsoNormal, div.MsoNormal {
                            margin:0;
                            margin-bottom:12.0pt;
                            line-height:115%;
                            font-size:11.0pt;
                            font-family:"Proxima Nova",sans-serif;
                            mso-fareast-language:EN-US;
                        }
                        p { margin:0; margin-bottom:12.0pt; line-height:115%; }
                        span.paragraph { line-height:115%; }
                        .spacer-10 { mso-line-height-rule:exactly; line-height:10.0pt; font-size:10.0pt; }
                        .line-height-10 { line-height:1.0; mso-line-height-rule:exactly; }
                        .line-height-15 { line-height:1.5; mso-line-height-rule:exactly; }
                        .line-height-20 { line-height:2.0; mso-line-height-rule:exactly; }
                        </style>
                        <![endif]-->
                    </head>
                    <body lang="EN-US" style="tab-interval:.5in">
                        ${element.innerHTML}
                    </body>
                    </html>
                `);
                doc.close();
                
                // Select content inside the iframe
                const range = doc.createRange();
                range.selectNodeContents(doc.body);
                
                const selection = iframe.contentWindow.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
                
                // Copy using this special iframe
                const success = doc.execCommand('copy');
                
                // Clean up
                document.body.removeChild(iframe);
                
                return success;
            } catch (error) {
                console.error('Outlook special format copying failed:', error);
                return false;
            }
        }

        function copyForGmail() {
            const content = document.getElementById('formatted-content');
            if (!content) {
                showToast('Nothing to copy', 'error');
                return;
            }
            
            // Clone the content
            const gmailClone = content.cloneNode(true);
            
            // Fix table widths consistency for Gmail
            const fixGmailTableWidths = function(element) {
                // Set the standard width that we'll use consistently
                const standardWidth = 750;
                
                // Find all tables that need width standardization
                const tables = element.querySelectorAll('table');
                tables.forEach(table => {
                    table.setAttribute('width', standardWidth);
                    table.style.width = standardWidth + 'px';
                    table.style.maxWidth = '100%';
                    
                    // Add extra attributes for Gmail compatibility
                    table.setAttribute('cellspacing', '0');
                    table.setAttribute('cellpadding', '0');
                    table.setAttribute('border', '0');
                    table.setAttribute('align', 'center');
                });
                
                // Ensure all images (especially header) have correct attributes
                const images = element.querySelectorAll('img');
                images.forEach(img => {
                    // For header images, ensure they're exactly the standard width
                    if (img.parentElement && 
                        (img.parentElement.tagName === 'TD' || 
                         img.parentElement.parentElement && img.parentElement.parentElement.tagName === 'TD')) {
                        
                        const imgWidth = standardWidth;
                        img.setAttribute('width', imgWidth);
                        img.style.width = imgWidth + 'px';
                        img.style.maxWidth = '100%';
                        img.style.display = 'block';
                        img.style.margin = '0 auto';
                        
                        // Force the parent cell to match
                        if (img.parentElement.tagName === 'TD') {
                            img.parentElement.setAttribute('width', standardWidth);
                            img.parentElement.style.width = standardWidth + 'px';
                        }
                    }
                });
                
                // Make sure the footer td also has the same width
                const footerTd = element.querySelector('tr:last-child td');
                if (footerTd) {
                    footerTd.setAttribute('width', standardWidth);
                    footerTd.style.width = standardWidth + 'px';
                    footerTd.style.maxWidth = '100%';
                }
                
                // Ensure all TDs in the main structure have the same width
                const mainTds = element.querySelectorAll('td');
                mainTds.forEach(td => {
                    if (td.parentElement && 
                        td.parentElement.parentElement && 
                        td.parentElement.parentElement.tagName === 'TABLE') {
                        
                        // If it's a top-level TD, standardize width
                        if (!td.hasAttribute('width')) {
                            td.setAttribute('width', standardWidth);
                            td.style.width = standardWidth + 'px';
                            td.style.maxWidth = '100%';
                        }
                    }
                });
                
                return element;
            };
            
            // Fix the Gmail-specific issues with consistent table widths
            const gmailReadyContent = fixGmailTableWidths(gmailClone);
            
            // Create a proper wrapper for Gmail
            const wrapper = document.createElement('div');
            wrapper.innerHTML = `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <style type="text/css">
                        body { margin: 0; padding: 0; }
                        table { border-collapse: collapse; width: 750px !important; max-width: 100% !important; }
                        img { max-width: 100% !important; display: block; }
                        .header-img { width: 750px !important; max-width: 100% !important; }
                        .footer-td { width: 750px !important; max-width: 100% !important; }
                        
                        /* Fix Gmail-specific rendering issues */
                        u + .body .gmail-fix { display: none !important; }
                        
                        /* Additional Gmail compatibility */
                        .ReadMsgBody { width: 100%; }
                        .ExternalClass { width: 100%; }
                        .ExternalClass * { line-height: 100%; }
                    </style>
                </head>
                <body class="body">
                    <!-- Gmail rendering fix -->
                    <div class="gmail-fix" style="white-space:nowrap; font:15px courier; line-height:0;">
                        &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
                        &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
                    </div>
                    
                    <!-- Centered container with fixed width -->
                    <div style="width:100%; max-width:750px; margin:0 auto;">
                        ${gmailReadyContent.outerHTML}
                    </div>
                </body>
                </html>
            `;
            
            // Use the modified content for copying
            copyWithFormattingPreserved(wrapper)
                .then(result => {
                    if (result.success) {
                        showToast('Content copied for Gmail with fixed table widths!', 'success');
                    } else {
                        // If the advanced method fails, try a fallback method
                        fallbackGmailCopy(gmailReadyContent);
                    }
                })
                .catch(error => {
                    console.error('Gmail copy failed:', error);
                    fallbackGmailCopy(gmailReadyContent);
                });
        }

        function fallbackGmailCopy(content) {
            try {
                // Create a text area with the HTML
                const textarea = document.createElement('textarea');
                textarea.value = content.outerHTML;
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                textarea.style.top = '0';
                document.body.appendChild(textarea);
                
                // Select and copy
                textarea.focus();
                textarea.select();
                
                const success = document.execCommand('copy');
                document.body.removeChild(textarea);
                
                if (success) {
                    showToast('Email content copied! Choose "Paste with formatting" in Gmail', 'success');
                } else {
                    showManualCopyInstructions();
                }
            } catch (err) {
                console.error('Gmail copy fallback failed:', err);
                showManualCopyInstructions();
            }
        }

        function copyAsCleanHtml() {
            const content = document.getElementById('formatted-content');
            if (!content) {
                showToast('Nothing to copy', 'error');
                return;
            }
            
            // Clone the content
            const cleanHtmlContainer = document.createElement('div');
            cleanHtmlContainer.innerHTML = content.innerHTML;
            
            // Create a blob with just the HTML
            const htmlBlob = new Blob([cleanHtmlContainer.innerHTML], { type: 'text/html' });
            
            // Try to use modern clipboard API first
            if (navigator.clipboard && navigator.clipboard.write) {
                try {
                    const clipboardItem = new ClipboardItem({
                        'text/html': htmlBlob
                    });
                    
                    navigator.clipboard.write([clipboardItem])
                        .then(() => {
                            showToast('HTML content copied for email! Paste with Ctrl+V', 'success');
                        })
                        .catch(err => {
                            fallbackCopyHtml(cleanHtmlContainer);
                        });
                } catch (err) {
                    fallbackCopyHtml(cleanHtmlContainer);
                }
            } else {
                fallbackCopyHtml(cleanHtmlContainer);
            }
        }

        /**
         * Fallback method for clean HTML copy
         */
        function fallbackCopyHtml(container) {
            try {
                // Create a selection range
                const range = document.createRange();
                range.selectNodeContents(container);
                
                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range);
                
                // Copy
                const success = document.execCommand('copy');
                selection.removeAllRanges();
                
                if (success) {
                    showToast('HTML content copied! Paste in your email client', 'success');
                } else {
                    showManualCopyInstructions();
                }
            } catch (err) {
                console.error('Clean HTML copy failed:', err);
                showManualCopyInstructions();
            }
        }
    </script>
    
    <!-- Additional script to ensure proper file uploads -->
    <script>
        // Helper function to check file upload capabilities
        function checkFileUploadSupport() {
            // Check if FormData is supported
            if (window.FormData === undefined) {
                console.error('FormData is not supported in this browser');
                return false;
            }
            
            // Check if FileReader is supported
            if (window.FileReader === undefined) {
                console.error('FileReader is not supported in this browser');
                return false;
            }
            
            return true;
        }
        
        // Initialize on page load
        window.addEventListener('load', function() {
            // Check file upload support
            const hasSupport = checkFileUploadSupport();
            console.log('File upload support:', hasSupport);
            
            // Verify form enctype again
            const form = document.querySelector('form');
            if (form) {
                // Force the correct enctype for the form
                form.setAttribute('enctype', 'multipart/form-data');
                
                // Add an extra validation step before submission
                form.addEventListener('submit', function(e) {
                    const debug = document.querySelector('[x-data]').__x.$data.debug;
                    if (debug) {
                        debug.messages.push('Form submission started');
                    }
                    
                    // Verify enctype again just to be safe
                    if (this.getAttribute('enctype') !== 'multipart/form-data') {
                        e.preventDefault();
                        this.setAttribute('enctype', 'multipart/form-data');
                        if (debug) {
                            debug.messages.push('Fixed missing enctype before submission');
                        }
                        
                        // Re-submit the form after fixing
                        setTimeout(() => this.submit(), 100);
                    }
                });
            }
        });
    </script>

<?php
/**
 * Generate a complete email template with all components
 * Using consistent width across header, body, and footer
 * 
 * @param array $options Configuration options for the template
 * @return string Complete HTML for the email template
 */
function generateEmailTemplate($options) {
    // Set default values
    $defaults = [
        'header_image' => '',
        'body_content' => '',
        'signature_name' => '',
        'signature_title' => '',
        'employee_images' => [],
        'layout_type' => '0',
        'footer_image' => '',
        'employee_details' => [],
        'group_image' => '',
        'group_caption' => '',
        'regards_text' => 'Best Regards,'
    ];
    
    // Merge provided options with defaults
    $options = array_merge($defaults, $options);
    
    // Extract variables for easier use
    extract($options);
    
    // Set font family - Use both "Proxima Nova RG" and "Proxima Nova" for better compatibility
    $fontFamily = '"Proxima Nova RG", "Proxima Nova", Arial, sans-serif';
    
    // The email width - IMPORTANT: Use the same width throughout for Gmail compatibility
    $emailWidth = 750;
    
    // Process the body content for better email compatibility
    $body_content = processContentForOutlook($body_content);
    
    // Start building the template with consistent structure for header, body, and footer
    $template = '<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:v="urn:schemas-microsoft-com:vml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />


<!--[if mso]>
<xml>
  <o:OfficeDocumentSettings>
    <o:AllowPNG/>
    <o:PixelsPerInch>96</o:PixelsPerInch>
  </o:OfficeDocumentSettings>
</xml>
<style type="text/css">
    /* Outlook-specific styling */
    body, table, td, p, a, li, blockquote {
        font-family: ' . $fontFamily . ' !important;
        mso-line-height-rule: exactly;
    }
    p, div, span, strong, em, h1, h2, h3, h4, h5, h6 {
        mso-line-height-rule: exactly;
        font-family: ' . $fontFamily . ' !important;
    }
    
    p {
        mso-margin-top-alt: 0;
        mso-margin-bottom-alt: 12.0pt;
        margin-top: 0;
        margin-bottom: 12.0pt;
    }
    
    /* Precise line height classes for Outlook */
    .line-height-10 { line-height:1.0 !important; mso-line-height-rule:exactly; mso-line-height-alt:16.0pt; margin-top:0; margin-bottom:12.0pt; }
    .line-height-15 { line-height:1.5 !important; mso-line-height-rule:exactly; mso-line-height-alt:24.0pt; margin-top:0; margin-bottom:12.0pt; }
    .line-height-20 { line-height:2.0 !important; mso-line-height-rule:exactly; mso-line-height-alt:32.0pt; margin-top:0; margin-bottom:12.0pt; }
    
    /* Additional spacer solution for Outlook */
    .spacer-5 { mso-line-height-rule:exactly; line-height:5.0pt; font-size:5.0pt; }
    .spacer-10 { mso-line-height-rule:exactly; line-height:10.0pt; font-size:10.0pt; }
    .spacer-15 { mso-line-height-rule:exactly; line-height:15.0pt; font-size:15.0pt; }
    .spacer-20 { mso-line-height-rule:exactly; line-height:20.0pt; font-size:20.0pt; }
    
    /* Special fix for Outlook line spacing issues */
    p.MsoNormal, li.MsoNormal, div.MsoNormal {
        margin: 0;
        margin-bottom: 12.0pt;
        line-height: 115%;
        font-size: 11.0pt;
        font-family: ' . $fontFamily . ';
    }
    
    table {
        mso-table-lspace: 0pt;
        mso-table-rspace: 0pt;
    }
</style>
<![endif]-->

<style type="text/css">
    body {
        margin: 0;
        padding: 0;
        -webkit-text-size-adjust: 100%;
        -ms-text-size-adjust: 100%;
        font-family: ' . $fontFamily . ' !important;
    }
    
    table, td {
        border-collapse: collapse;
        mso-table-lspace: 0pt;
        mso-table-rspace: 0pt;
        font-family: ' . $fontFamily . ' !important;
    }
    
    img {
        -ms-interpolation-mode: bicubic;
        border: 0;
        height: auto;
        line-height: 100%;
        outline: none;
        text-decoration: none;
    }
    
    p, div, span, strong, em, h1, h2, h3, h4, h5, h6, a, li {
        font-family: ' . $fontFamily . ' !important;
    }
    
    /* Better line spacing preservation */
    [style*="line-height"] {
        line-height: inherit !important;
    }
    
    /* Default paragraph styling if not specified in the editor */
    p {
        margin-top: 0;
        margin-bottom: 12pt;
        line-height: 1.5;
    }
    
    /* Line height classes */
    .line-height-10 { line-height:1.0 !important; }
    .line-height-15 { line-height:1.5 !important; }
    .line-height-20 { line-height:2.0 !important; }
    
    /* Spacer classes */
    .spacer-5 { line-height:5px; font-size:5px; }
    .spacer-10 { line-height:10px; font-size:10px; }
    .spacer-15 { line-height:15px; font-size:15px; }
    .spacer-20 { line-height:20px; font-size:20px; }
</style>
</head>
<body style="margin:0;padding:0;background-color:#ffffff;font-family:' . $fontFamily . ' !important;">
<!--[if mso]>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" width="' . $emailWidth . '" style="width:' . $emailWidth . 'px;">
<tr>
<td>
<![endif]-->
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" width="' . $emailWidth . '" style="width:' . $emailWidth . 'px;max-width:100%;border-collapse:collapse;border-spacing:0;margin:0 auto;padding:0;font-family:' . $fontFamily . ' !important;">
    <!-- HEADER SECTION -->
    <tr>
        <td align="center" style="padding:0;font-family:' . $fontFamily . ' !important;" width="' . $emailWidth . '">
            ' . ($header_image ? '
            <img src="' . $header_image . '" alt="Header" width="' . $emailWidth . '" style="width:' . $emailWidth . 'px;max-width:100%;display:block;border:0;margin:0 auto;" />'
            : '') . '
        </td>
    </tr>
    
    <!-- BODY CONTENT SECTION -->
    <tr>
        <td class="outlook-content-cell" align="center" style="padding:30px 50px;font-family:' . $fontFamily . ' !important;" width="' . $emailWidth . '">
            <div style="width:100%;font-family:' . $fontFamily . ' !important;line-height:1.5 !important;mso-line-height-rule:exactly;mso-line-height-alt:24pt;">
                ' . $body_content . '
            </div>
        </td>
    </tr>';

    // Add employee images or group image based on layout type
    if ($layout_type === 'group' && !empty($group_image)) {
        $template .= generateGroupImage($group_image, $group_caption, $fontFamily, $emailWidth);
    } else if (!empty($employee_images)) {
        $template .= generateImageGrid($employee_images, $layout_type, $employee_details, $fontFamily, $emailWidth);
    }

    // Add signature section
    $template .= '
    <!-- OUTLOOK SPACING FIX - BEFORE SIGNATURE -->
    <tr>
        <td height="-5" style="font-size:-5px;line-height:-5px;mso-line-height-rule:exactly;">&nbsp;</td>
    </tr>
    
    <tr>
        <td align="left" style="padding:10px 50px 10px 50px;font-family:' . $fontFamily . ' !important;" width="' . $emailWidth . '">
            <p style="margin:0 0 12pt 0;padding:0;line-height:1.5 !important;mso-line-height-rule:exactly;mso-line-height-alt:24pt;color:#333333;font-family:' . $fontFamily . ' !important;">
                <strong style="font-weight:bold;font-family:' . $fontFamily . ' !important;">' . htmlspecialchars($regards_text) . '</strong>
            </p>
            <p style="margin:0 0 12pt 0;padding:0;line-height:1.5 !important;mso-line-height-rule:exactly;mso-line-height-alt:24pt;color:#333333;font-family:' . $fontFamily . ' !important;">
                <strong style="font-weight:bold;font-family:' . $fontFamily . ' !important;">' . htmlspecialchars($signature_name) . '</strong><br>
                <span style="color:#666666;font-weight:bold;font-family:' . $fontFamily . ' !important;">' . htmlspecialchars($signature_title) . '</span>
            </p>
        </td>
    </tr>
    
    <!-- OUTLOOK SPACING FIX - BEFORE FOOTER -->
    <tr>
        <td height="5" style="font-size:5px;line-height:5px;mso-line-height-rule:exactly;">&nbsp;</td>
    </tr>
    
    <!-- FOOTER SECTION -->
    <tr>
        <td align="center" style="background-color:#000000;padding:15px;font-family:' . $fontFamily . ' !important;" width="' . $emailWidth . '">
            <div style="font-family:' . $fontFamily . ' !important;line-height:1.5 !important;mso-line-height-rule:exactly;mso-line-height-alt:24pt;color:#FFFFFF;text-align:center;">
                <span style="color:#FFFFFF;font-family:' . $fontFamily . ' !important;">
                    Padak (Pvt) Ltd, Batticaloa, Sri Lanka,<br>
                    Contact Number: +94 710815522
                </span>
            </div>
        </td>
    </tr>
</table>
<!--[if mso]>
</td>
</tr>
</table>
<![endif]-->
</body>
</html>';

    return $template;
}

/**
 * Generate HTML for a group image section
 */
function generateGroupImage($group_image, $group_caption, $fontFamily = null, $emailWidth = 750) {
    if (empty($group_image)) {
        return '';
    }
    
    // Set font family if not provided
    if ($fontFamily === null) {
        $fontFamily = '"Proxima Nova RG", "Proxima Nova", Arial, sans-serif';
    }
    
    $imageWidth = 600; // This keeps it proportional but smaller than the main width
    
    $html = '<tr><td align="center" style="padding:20px 0;font-family:' . $fontFamily . ' !important;" width="' . $emailWidth . '">';
    $html .= '<div style="text-align:center; max-width:' . $imageWidth . 'px; margin:0 auto;font-family:' . $fontFamily . ' !important;">';
    // Make the group photo wider than individual photos but still less than the email width
    $html .= '<img src="' . $group_image . '" width="' . $imageWidth . '" style="width:' . $imageWidth . 'px; max-width:100%; display:block; border:0; margin:0 auto; border-radius:5px;" />';
    
    if (!empty($group_caption)) {
        $html .= '<div style="padding-top:15px; font-family:' . $fontFamily . ' !important;">';
        
        // Add MSO conditional comment for Outlook
        $html .= '<!--[if mso]><font face="' . $fontFamily . '"><![endif]-->';
        
        $html .= '<span style="font-style:italic; font-size:14px; font-family:' . $fontFamily . ' !important;">' . 
                 htmlspecialchars($group_caption) . '</span>';
                 
        $html .= '<!--[if mso]></font><![endif]-->';
        
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</td></tr>';
    
    return $html;
}

/**
 * Generate HTML for an employee image grid
 * Improved to handle proper alignment based on number of images
 */
function generateImageGrid($images, $layout_type, $employee_details = [], $fontFamily = null, $emailWidth = 750) {
    // Filter out empty images but keep track of their positions
    $filteredImages = [];
    $validPositions = [];
    foreach ($images as $index => $imageData) {
        if (!empty($imageData)) {
            $filteredImages[] = $imageData;
            $validPositions[] = $index;
        }
    }
    
    // If no valid images, return empty
    if (empty($filteredImages)) {
        return '';
    }
    
    // Set font family if not provided
    if ($fontFamily === null) {
        $fontFamily = '"Proxima Nova RG", "Proxima Nova", Arial, sans-serif';
    }
    
    $html = '<tr><td align="center" width="' . $emailWidth . '" style="font-family:' . $fontFamily . ' !important;"><table cellspacing="0" cellpadding="0" border="0" align="center" width="100%" style="width:100%; border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt; font-family:' . $fontFamily . ' !important;">';
    
    $generateEmployeeCell = function($index, $imageData, $details, $cellWidth = 200) use ($fontFamily) {
        $html = '<td align="center" style="padding:10px; width:' . $cellWidth . 'px; font-family:' . $fontFamily . ' !important;">';
        $html .= '<div style="text-align:center; font-family:' . $fontFamily . ' !important;">';
        
        // Only include image if there's actual data
        if (!empty($imageData)) {
            $html .= '<img src="' . $imageData . '" width="200" height="200" style="display:block; border:0; margin:0 auto; border-radius:5px;" />';
            $html .= '<div style="height:15px; line-height:15px; font-size:15px;">&nbsp;</div>';
        }
        
        if (isset($details[$index]) && !empty($details[$index]['name'])) {
            $html .= '<div style="padding-top:15px; font-family:' . $fontFamily . ' !important;">';
            $html .= '<span style="font-weight:bold; font-size:16px; font-family:' . $fontFamily . ' !important;">' . 
                     htmlspecialchars($details[$index]['name']) . '</span><br>';
                     
            // Always include a font tag for Outlook compatibility
            $html .= '<!--[if mso]><font face="' . $fontFamily . '"><![endif]-->';
            $html .= '<span style="font-size:14px; font-family:' . $fontFamily . ' !important;">' . 
                     htmlspecialchars($details[$index]['title']) . '</span>';
            $html .= '<!--[if mso]></font><![endif]-->';
            
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</td>';
        
        return $html;
    };
    
    // Calculate number of valid images
    $validImageCount = count($filteredImages);
    
    // Special handling based on actual number of images
    if ($validImageCount === 1) {
        // Single image is always centered
        $html .= '<tr><td align="center"><table cellpadding="0" cellspacing="0" border="0"><tr>';
        $html .= $generateEmployeeCell($validPositions[0], $filteredImages[0], $employee_details, 200);
        $html .= '</tr></table></td></tr>';
    } 
    else if ($validImageCount === 2) {
        // Two images side by side, centered
        $html .= '<tr><td align="center"><table cellpadding="0" cellspacing="0" border="0"><tr>';
        $html .= $generateEmployeeCell($validPositions[0], $filteredImages[0], $employee_details, 200);
        $html .= $generateEmployeeCell($validPositions[1], $filteredImages[1], $employee_details, 200);
        $html .= '</tr></table></td></tr>';
    }
    else if ($validImageCount === 3) {
        // Three images in a row
        $html .= '<tr><td align="center"><table cellpadding="0" cellspacing="0" border="0"><tr>';
        for ($i = 0; $i < 3; $i++) {
            $html .= $generateEmployeeCell($validPositions[$i], $filteredImages[$i], $employee_details, 200);
        }
        $html .= '</tr></table></td></tr>';
    }
    else if ($validImageCount === 4) {
        // 2x2 grid
        // First row - 2 images
        $html .= '<tr><td align="center"><table cellpadding="0" cellspacing="0" border="0"><tr>';
        for ($i = 0; $i < 2; $i++) {
            $html .= $generateEmployeeCell($validPositions[$i], $filteredImages[$i], $employee_details, 200);
        }
        $html .= '</tr></table></td></tr>';
        
        // Second row - 2 images
        $html .= '<tr><td align="center"><table cellpadding="0" cellspacing="0" border="0"><tr>';
        for ($i = 2; $i < 4; $i++) {
            $html .= $generateEmployeeCell($validPositions[$i], $filteredImages[$i], $employee_details, 200);
        }
        $html .= '</tr></table></td></tr>';
    }
    else if ($validImageCount === 5) {
        // First row - 3 images
        $html .= '<tr><td align="center"><table cellpadding="0" cellspacing="0" border="0"><tr>';
        for ($i = 0; $i < 3; $i++) {
            $html .= $generateEmployeeCell($validPositions[$i], $filteredImages[$i], $employee_details, 200);
        }
        $html .= '</tr></table></td></tr>';
        
        // Second row - 2 images (centered)
        $html .= '<tr><td align="center"><table cellpadding="0" cellspacing="0" border="0"><tr>';
        $html .= '<td width="100" style="font-family:' . $fontFamily . ' !important;">&nbsp;</td>'; // Spacer for centering
        for ($i = 3; $i < 5; $i++) {
            $html .= $generateEmployeeCell($validPositions[$i], $filteredImages[$i], $employee_details, 200);
        }
        $html .= '<td width="100" style="font-family:' . $fontFamily . ' !important;">&nbsp;</td>'; // Spacer for centering
        $html .= '</tr></table></td></tr>';
    }
    else if ($validImageCount > 5) {
        // Handle more than 5 images in rows of 3
        for ($row = 0; $row < ceil($validImageCount / 3); $row++) {
            $html .= '<tr><td align="center"><table cellpadding="0" cellspacing="0" border="0"><tr>';
            for ($col = 0; $col < 3; $col++) {
                $index = $row * 3 + $col;
                if ($index < $validImageCount) {
                    $html .= $generateEmployeeCell($validPositions[$index], $filteredImages[$index], $employee_details, 200);
                }
            }
            $html .= '</tr></table></td></tr>';
        }
    }
    
    $html .= '</table></td></tr>';
    
    return $html;
}

/**
 * Process HTML content for Outlook compatibility
 */
function processContentForOutlook($html) {
    // If the HTML is empty, return
    if (empty($html)) {
        return '';
    }
    
    // Set font family to ensure both Proxima Nova RG and regular Proxima Nova are included
    $fontFamily = '"Proxima Nova RG", "Proxima Nova", Arial, sans-serif';
    
    // Create a DOMDocument to process the HTML
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = true;
    
    // Load HTML with error suppression
    libxml_use_internal_errors(true); 
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    // Create a DOMXPath object to query the document
    $xpath = new DOMXPath($dom);
    
    // Process ALL elements to ensure font and spacing is consistently applied
    $allElements = $xpath->query('//*');
    if ($allElements) {
        foreach ($allElements as $element) {
            $style = $element->getAttribute('style');
            $tagName = strtolower($element->nodeName);
            
            // Parse existing style attributes
            $styles = array();
            if (!empty($style)) {
                // Split the style attribute by semicolons
                $styleAttrs = explode(';', $style);
                foreach ($styleAttrs as $attr) {
                    $attr = trim($attr);
                    if (empty($attr)) continue;
                    
                    // Split each style declaration into property and value
                    $parts = explode(':', $attr, 2);
                    if (count($parts) == 2) {
                        $property = trim(strtolower($parts[0]));
                        $value = trim($parts[1]);
                        $styles[$property] = $value;
                    }
                }
            }
            
            // Always ensure font-family is set
            $styles['font-family'] = $fontFamily . ' !important';
            
            // Check for line-height style and make it properly compatible with Outlook
            $hasLineHeight = isset($styles['line-height']);
            $lineHeightValue = $hasLineHeight ? preg_replace('/\s*!important\s*/', '', $styles['line-height']) : null;
            
            // For common text elements, ensure proper line height handling
            if (in_array($tagName, ['p', 'div', 'span', 'strong', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'td', 'th', 'a', 'li'])) {
                // Only add default line height if none is specified
                if (!$hasLineHeight) {
                    $styles['line-height'] = '1.5 !important';
                    $lineHeightValue = '1.5';
                } else {
                    // Make sure the line-height is preserved with !important
                    $lineHeight = $lineHeightValue;
                    $styles['line-height'] = $lineHeight . ' !important';
                }
                
                // Add MSO rule for Outlook
                $styles['mso-line-height-rule'] = 'exactly';
                
                // Convert unitless line height to pt for Outlook compatibility
                // This is critical for Outlook to interpret line heights correctly
                if (is_numeric($lineHeightValue) || preg_match('/^[0-9.]+$/', $lineHeightValue)) {
                    $numericValue = floatval($lineHeightValue);
                    $ptValue = $numericValue * 16; // Use 16pt as base font size for better results
                    $styles['mso-line-height-alt'] = $ptValue . 'pt';
                }
                
                // Add margin control for paragraphs for better spacing
                if ($tagName == 'p') {
                    if (!isset($styles['margin-top'])) {
                        $styles['margin-top'] = '0';
                    }
                    if (!isset($styles['margin-bottom'])) {
                        $styles['margin-bottom'] = '12pt';
                    }
                }
            }
            
            // Outlook-specific class for paragraphs to help with Line Spacing
            if ($tagName == 'p') {
                // Add a class for paragraphs with specific line heights
                if ($hasLineHeight) {
                    $currentClass = $element->getAttribute('class') ?? '';
                    $lineHeightClasses = [];
                    
                    // Remove any existing line-height classes
                    $existingClasses = explode(' ', $currentClass);
                    foreach ($existingClasses as $class) {
                        if (strpos($class, 'line-height-') !== 0) {
                            $lineHeightClasses[] = $class;
                        }
                    }
                    
                    // Add appropriate line height class based on value
                    $numericValue = floatval($lineHeightValue);
                    if ($numericValue <= 1.2) {
                        $lineHeightClasses[] = 'line-height-10';
                    } else if ($numericValue <= 1.8) {
                        $lineHeightClasses[] = 'line-height-15';
                    } else {
                        $lineHeightClasses[] = 'line-height-20';
                    }
                    
                    // Add MsoNormal class for Outlook
                    $lineHeightClasses[] = 'MsoNormal';
                    
                    $element->setAttribute('class', implode(' ', $lineHeightClasses));
                }
            }
            
            // Rebuild the style attribute
            $newStyle = '';
            foreach ($styles as $property => $value) {
                $newStyle .= $property . ': ' . $value . '; ';
            }
            
            // Set the modified style back
            $element->setAttribute('style', trim($newStyle));
            
            // If the element is a <font> tag, explicitly set the face attribute as well
            if ($tagName === 'font') {
                $element->setAttribute('face', $fontFamily);
            }
        }
    }
    
    // Get the processed HTML content
    $html = $dom->saveHTML();
    
    // Remove the XML declaration
    $html = preg_replace('/<\?xml encoding="utf-8" \?>/i', '', $html);
    
    // Remove unnecessary HTML, head, body tags if they were added by DOMDocument
    $html = preg_replace('/<\/?html[^>]*>/i', '', $html);
    $html = preg_replace('/<\/?head[^>]*>/i', '', $html);
    $html = preg_replace('/<\/?body[^>]*>/i', '', $html);
    
    return $html;
}

/**
 * Format announcement content by highlighting important phrases
 */
function formatAnnouncementContent($content) {
    // Common important phrases to bold
    $importantPhrases = [
        // Dates and Time-related
        '/\b(today|tomorrow|yesterday)\b/i',
        '/\b(\d{1,2}(?:st|nd|rd|th)?\s+(?:January|February|March|April|May|June|July|August|September|October|November|December))\b/i',
        '/\b(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\b/i',
        
        // Event keywords
        '/\b(meeting|webinar|conference|seminar|workshop|training)\b/i',
        '/\b(announcement|attention|notice|reminder|update|alert)\b/i',
        
        // Action items
        '/\b(deadline|due date|required|mandatory|important|urgent|asap|action required)\b/i',
        '/\b(please note|key points|highlights)\b/i',
        
        // Time
        '/\b(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)\b/',
        
        // Numbers with context
        '/\b(Phase [0-9]+)\b/i',
        '/\b(Step [0-9]+)\b/i'
    ];
    
    // Additional context-based patterns
    $contextPatterns = [
        // Time periods
        '/\b(\d+[:]\\d+\\s*(?:AM|PM|am|pm))\b/',
        // Dates in various formats
        '/\b(\\d{1,2}[-\/]\\d{1,2}[-\/]\\d{2,4})\b/',
        // Percentages
        '/\b(\\d+(?:\\.\\d+)?%)\b/',
        // Currency
        '/\b(\\$\\d+(?:,\\d{3})*(?:\\.\\d{2})?)\b/',
        // Email addresses
        '/\b([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,})\b/'
    ];
    
    // Combine all patterns
    $allPatterns = array_merge($importantPhrases, $contextPatterns);
    
    // Apply bold formatting to matches
    foreach ($allPatterns as $pattern) {
        $content = preg_replace($pattern, '<strong>$1</strong>', $content);
    }
    
    // Handle special cases - CAPITALIZE and bold certain keywords
    $emphasisKeywords = [
        'IMPORTANT',
        'URGENT',
        'REMINDER',
        'NOTE',
        'WARNING',
        'ALERT',
        'ACTION REQUIRED',
        'DEADLINE'
    ];
    
    foreach ($emphasisKeywords as $keyword) {
        $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
        $content = preg_replace($pattern, '<strong>' . strtoupper($keyword) . '</strong>', $content);
    }
    
    // Preserve existing styles
    $content = preg_replace_callback('/<([a-z]+)([^>]*?)style="([^"]*?)"([^>]*?)>/i', function($matches) {
        // Keep the style attribute intact
        return '<' . $matches[1] . $matches[2] . 'style="' . $matches[3] . '"' . $matches[4] . '>';
    }, $content);

    return $content;
}
?>

<script>
window.addEventListener('load', function() {
    if (typeof tinymce === 'undefined') {
        console.error('TinyMCE failed to load');
        alert('Editor failed to load. Please check your internet connection.');
    } else {
        console.log('TinyMCE loaded successfully');
    }
});
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
    // Get the formatted content container
    const previewContainer = document.getElementById('formatted-content');
    
    if (previewContainer) {
        // Add a click handler to make the container selectable
        previewContainer.addEventListener('click', function(e) {
            // Set a data attribute to mark this container as "active" for selection
            this.setAttribute('data-active-container', 'true');
        });
        
        // Add a blur handler to remove the "active" marker when clicking outside
        document.addEventListener('click', function(e) {
            if (!previewContainer.contains(e.target)) {
                previewContainer.removeAttribute('data-active-container');
            }
        });
        
        // Intercept Ctrl+A keypress
        document.addEventListener('keydown', function(e) {
            // Check if Ctrl+A was pressed (or Command+A on Mac)
            if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
                // Check if our preview container is active
                if (previewContainer.getAttribute('data-active-container') === 'true') {
                    e.preventDefault(); // Prevent default Ctrl+A behavior
                    
                    // Create a selection range for just the preview content
                    const range = document.createRange();
                    range.selectNodeContents(previewContainer);
                    
                    const selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
            }
        });
        
        // Add visual indicator for better user experience
        previewContainer.addEventListener('mouseenter', function() {
            // Add a subtle outline when hovering over the preview
            if (!this.style.outline) {
                this.style.transition = 'outline 0.2s ease-in-out';
                this.style.outline = '1px dashed rgba(79, 70, 229, 0.3)';
            }
        });
        
        previewContainer.addEventListener('mouseleave', function() {
            // Remove outline when not hovering
            if (!this.getAttribute('data-active-container')) {
                this.style.outline = 'none';
            }
        });
        
        // Make the behavior clearer with a tooltip
        previewContainer.setAttribute('title', 'Click inside and press Ctrl+A to select all content');
    }
    
    // Also fix the manual copy instructions to be clearer
    const instructionsElement = document.getElementById('manual-copy-instructions');
    if (instructionsElement) {
        const instructions = instructionsElement.querySelector('ol li:first-child');
        if (instructions) {
            instructions.innerHTML = 'Click inside the preview area above (it will show a subtle outline when active)';
        }
    }
});
</script>
</body>
</html>
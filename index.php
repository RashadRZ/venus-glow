<?php

require 'vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Upload Photo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['image'])) {
    $upload_dir = "uploads/";
    $output_dir = "outputs/";

    // Get the uploaded file
    $uploaded_file = $upload_dir . basename($_FILES["image"]["name"]);
    $output_file = $output_dir . basename($_FILES["image"]["name"]);

    // Move the uploaded file to the uploads directory
    if (move_uploaded_file($_FILES["image"]["tmp_name"], $uploaded_file)) {
        // Run the Python script with the uploaded image and output path
        $command = escapeshellcmd("all.py " . escapeshellarg($uploaded_file) . " " . escapeshellarg($output_file));
        $output = shell_exec($command);

        // Decode the JSON output from Python
        $colors = json_decode($output, true);

        $data1 = [];
        $data2 = [];
        $data3 = [];

        if ($colors) {
            foreach ($colors as $color_info) {
                if (strpos($color_info['landmark'], 'eyebrow') !== false) {
                    $data1[] = $color_info;
                } elseif (strpos($color_info['landmark'], 'cheek') !== false) {
                    $data2[] = $color_info;
                } elseif (strpos($color_info['landmark'], 'lips') !== false) {
                    $data3[] = $color_info;
                }
            }
        } else {
            echo "Error processing the image.";
        }
    } else {
        echo "Error uploading the file.";
    }

    // Gemini AI
    $api_key = $_ENV['GEMINI_API_KEY'];

    // API URL for Gemini Model
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;

    // Data to Send
    $data_eyebrow_pencil = [
        'contents' => [
            ['parts' => [['text' => "Recommend me a eyebrows pencil color based on this eyebrows colors: {$data1[0]['color_hex']}, {$data1[1]['color_hex']}. Just give me a color"]]]
        ]
    ];

    $data_blush = [
        'contents' => [
            ['parts' => [['text' => "Recommend me a blush color based on this cheeks colors: {$data2[0]['color_hex']}, {$data2[1]['color_hex']}. Just give me a color"]]]
        ]
    ];

    $data_lipstick = [
        'contents' => [
            ['parts' => [['text' => "Recommend me a lipstick color based on this lips colors: {$data3[0]['color_hex']}, {$data3[1]['color_hex']}, {$data3[2]['color_hex']}. Just give me a color"]]]
        ]
    ];

    // cURL Initialization
    $ch = curl_init($url);

    // Set cURL Options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_eyebrow_pencil));
    $response1 = curl_exec($ch);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_blush));
    $response2 = curl_exec($ch);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_lipstick));
    $response3 = curl_exec($ch);

    // Close cURL
    curl_close($ch);

    // Decode JSON Response
    $responseDataEyebrowPencil = json_decode($response1, true);
    $responseDataBlush = json_decode($response2, true);
    $responseDataLipstick = json_decode($response3, true);

    $textEyebrowPencil = $responseDataEyebrowPencil['candidates'][0]['content']['parts'][0]['text'];
    $textBlush = $responseDataBlush['candidates'][0]['content']['parts'][0]['text'];
    $textLipstick = $responseDataLipstick['candidates'][0]['content']['parts'][0]['text'];
    $textWithPlusEyebrowPencil = urlencode($textEyebrowPencil);
    $textWithPlusBlush = urlencode($textBlush);
    $textWithPlusLipstick = urlencode($textLipstick);
    // END Gemini AI

    // Amazon API
    $amazon_api_key = $_ENV['AMAZON_API_KEY'];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://real-time-amazon-data.p.rapidapi.com/search?query=" . $textWithPlusEyebrowPencil . "%20eyebrow%20pencil&page=1&country=US&sort_by=RELEVANCE&product_condition=ALL&is_prime=false&deals_and_discounts=NONE",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "x-rapidapi-key: " . $amazon_api_key,
            "x-rapidapi-host: real-time-amazon-data.p.rapidapi.com"
        ],
    ]);

    $response1 = curl_exec($curl);
    $data_eyebrow_pencil = json_decode($response1, true);
    $products_eyebrow_pencil = array_slice($data_eyebrow_pencil['data']['products'], 0, 4);

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://real-time-amazon-data.p.rapidapi.com/search?query=" . $textWithPlusBlush . "%20blush&page=1&country=US&sort_by=RELEVANCE&product_condition=ALL&is_prime=false&deals_and_discounts=NONE",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "x-rapidapi-key: " . $amazon_api_key,
            "x-rapidapi-host: real-time-amazon-data.p.rapidapi.com"
        ],
    ]);
    $response2 = curl_exec($curl);
    $data_blush = json_decode($response2, true);
    $products_blush = array_slice($data_blush['data']['products'], 0, 4);

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://real-time-amazon-data.p.rapidapi.com/search?query=" . $textWithPlusLipstick . "%20lipstick&page=1&country=US&sort_by=RELEVANCE&product_condition=ALL&is_prime=false&deals_and_discounts=NONE",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "x-rapidapi-key: " . $amazon_api_key,
            "x-rapidapi-host: real-time-amazon-data.p.rapidapi.com"
        ],
    ]);
    $response3 = curl_exec($curl);
    $data_lipstick = json_decode($response3, true);
    $products_lipstick = array_slice($data_lipstick['data']['products'], 0, 4);

    curl_close($curl);

    // END Amazon API
}

// Ambil Nama Color
function getColorName($hexCode)
{
    $hexCode = ltrim($hexCode, '#');

    $apiUrl = "https://www.thecolorapi.com/id?hex={$hexCode}";
    $response = file_get_contents($apiUrl);

    $data = json_decode($response);
    return $data->name->value;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Venus Glow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        .picture-placeholder {
            width: 240px;
            height: 300px;
            background-color: #f0f0f0;
            border: 2px dashed #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #999;
        }

        .color-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin: 10px;
            border: 2px solid #ccc;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand mx-auto fw-bold fs-4" href="#">VENUS GLOW</a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container my-4">
        <div class="row">
            <!-- Column 1 -->
            <div class="col-md-3 text-center">
                <form action="" method="POST" enctype="multipart/form-data">
                    <?php if (isset($output_file)) { ?>
                        <img src="<?php echo $output_file; ?>" alt="Uploaded Image" class="picture-placeholder" />
                    <?php } else { ?>
                        <div class="picture-placeholder">Upload Image</div>
                    <?php } ?>
                    <div class="mt-3">
                        <input type="file" name="image" class="form-control mb-2" />
                        <button class="btn btn-primary">Submit</button>
                    </div>
                </form>
            </div>

            <!-- Column 2 -->
            <div class="col-md-6">
                <!-- Eyebrow Colors -->
                <div class="mb-4">
                    <h5>Eyebrow Colors</h5>
                    <div class="d-flex">
                        <?php
                        if (!empty($data1)) {
                            foreach ($data1 as $eyebrow) { ?>
                                <a href="#" data-bs-toggle="tooltip"
                                    data-bs-title="Color Name: <?php echo getColorName($eyebrow['color_hex']) ?><br>HEX Code: <?php echo $eyebrow['color_hex'] ?>">
                                    <div class="color-circle" style="background-color: <?php echo $eyebrow['color_hex'] ?>">
                                    </div>
                                </a>
                            <?php }
                        } else { ?>
                            <div class="color-circle" style="background-color: white"></div>
                            <div class="color-circle" style="background-color: white"></div>
                        <?php }
                        ?>
                    </div>
                </div>

                <!-- Cheek Colors -->
                <div class="mb-4">
                    <h5>Cheek Colors</h5>
                    <div class="d-flex">
                        <?php
                        if (!empty($data2)) {
                            foreach ($data2 as $cheek) { ?>
                                <a href="#" data-bs-toggle="tooltip"
                                    data-bs-title="Color Name: <?php echo getColorName($cheek['color_hex']) ?><br>HEX Code: <?php echo $cheek['color_hex'] ?>">
                                    <div class="color-circle" style="background-color: <?php echo $cheek['color_hex'] ?>">
                                    </div>
                                </a>
                            <?php }
                        } else { ?>
                            <div class="color-circle" style="background-color: white"></div>
                            <div class="color-circle" style="background-color: white"></div>
                        <?php }
                        ?>
                    </div>
                </div>

                <!-- Lip Colors -->
                <div>
                    <h5>Lip Colors</h5>
                    <div class="d-flex">
                        <?php
                        if (!empty($data3)) {
                            foreach ($data3 as $lips) { ?>
                                <a href="#" data-bs-toggle="tooltip"
                                    data-bs-title="Color Name: <?php echo getColorName($lips['color_hex']) ?><br>HEX Code: <?php echo $lips['color_hex'] ?>">
                                    <div class="color-circle" style="background-color: <?php echo $lips['color_hex'] ?>">
                                    </div>
                                </a>
                            <?php }
                        } else { ?>
                            <div class="color-circle" style="background-color: white"></div>
                            <div class="color-circle" style="background-color: white"></div>
                            <div class="color-circle" style="background-color: white"></div>
                        <?php }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Tabs -->
    <div class="container my-5">
        <!-- Tabs -->
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="eyebrow-tab" data-bs-toggle="tab" data-bs-target="#eyebrow"
                    type="button" role="tab">
                    Eyebrows Pencil
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="cheek-tab" data-bs-toggle="tab" data-bs-target="#cheek" type="button"
                    role="tab">Blush</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="lips-tab" data-bs-toggle="tab" data-bs-target="#lips" type="button"
                    role="tab">Lipstick</button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content mt-3">
            <!-- Eyebrows Products -->
            <div class="tab-pane fade" id="eyebrow" role="tabpanel">
                <div class="row mt-4">
                    <?php foreach ($products_eyebrow_pencil as $row) { ?>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="card sharp-shadow p-2">
                                <img src="<?php echo $row['product_photo'] ?>" class="w-100"
                                    style="aspect-ratio: 1 / 1; object-fit: cover" />
                                <a href="<?php echo $row['product_url'] ?>">
                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-1"><?php echo $row['product_title'] ?></h6>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <small><?php echo $row['product_price'] ?></small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- Cheek Products -->
            <div class="tab-pane fade" id="cheek" role="tabpanel">
                <div class="row mt-4">
                    <?php foreach ($products_blush as $row) { ?>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="card sharp-shadow p-2">
                                <img src="<?php echo $row['product_photo'] ?>" class="w-100"
                                    style="aspect-ratio: 1 / 1; object-fit: cover" />
                                <a href="<?php echo $row['product_url'] ?>">
                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-1"><?php echo $row['product_title'] ?></h6>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <small><?php echo $row['product_price'] ?></small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- Lips Products -->
            <div class="tab-pane fade" id="lips" role="tabpanel">
                <div class="row mt-4">
                    <?php foreach ($products_lipstick as $row) { ?>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="card sharp-shadow p-2">
                                <img src="<?php echo $row['product_photo'] ?>" class="w-100"
                                    style="aspect-ratio: 1 / 1; object-fit: cover" />
                                <a href="<?php echo $row['product_url'] ?>">
                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-1"><?php echo $row['product_title'] ?></h6>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <small><?php echo $row['product_price'] ?></small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        document.addEventListener('DOMContentLoaded', function () {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                new bootstrap.Tooltip(tooltipTriggerEl, {
                    html: true
                });
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
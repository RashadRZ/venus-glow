import cv2
import mediapipe as mp
import json
import sys
import os

# Initialize MediaPipe
mp_face_mesh = mp.solutions.face_mesh

def bgr_to_hex(bgr):
    return "#{:02x}{:02x}{:02x}".format(int(bgr[2]), int(bgr[1]), int(bgr[0]))

def analyze_face_colors(image_path, output_path):
    image = cv2.imread(image_path)
    image_rgb = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
    face_colors = []

    with mp_face_mesh.FaceMesh(min_detection_confidence=0.5) as face_mesh:
        results = face_mesh.process(image_rgb)

        if results.multi_face_landmarks:
            for face_landmarks in results.multi_face_landmarks:
                lips_indices = [78, 81, 84]
                cheeks_indices = [425, 50]
                eyebrow_indices = [295, 53]

                def get_average_color(landmark_index, offset=5):
                    landmark = face_landmarks.landmark[landmark_index]
                    h, w, _ = image.shape
                    x = int(landmark.x * w)
                    y = int(landmark.y * h)

                    x1 = max(0, x - offset)
                    x2 = min(w, x + offset)
                    y1 = max(0, y - offset)
                    y2 = min(h, y + offset)

                    return cv2.mean(image[y1:y2, x1:x2])[:3]  # BGR

                # Collecting lip colors
                for index in lips_indices:
                    lip_color_bgr = get_average_color(index)
                    lip_color_hex = bgr_to_hex(lip_color_bgr)
                    face_colors.append({'landmark': f'lips_{index}', 'color_hex': lip_color_hex})

                # Collecting cheek colors
                for index in cheeks_indices:
                    cheek_color_bgr = get_average_color(index)
                    cheek_color_hex = bgr_to_hex(cheek_color_bgr)
                    face_colors.append({'landmark': f'cheek_{index}', 'color_hex': cheek_color_hex})

                # Collecting eyebrow colors
                for index in eyebrow_indices:
                    eyebrow_color_bgr = get_average_color(index)
                    eyebrow_color_hex = bgr_to_hex(eyebrow_color_bgr)
                    face_colors.append({'landmark': f'eyebrow_{index}', 'color_hex': eyebrow_color_hex})

                # Drawing landmarks for visualization
                for index in lips_indices:
                    landmark = face_landmarks.landmark[index]
                    h, w, _ = image.shape
                    x, y = int(landmark.x * w), int(landmark.y * h)
                    cv2.circle(image, (x, y), 2, (0, 255, 0), -1)

                for index in cheeks_indices:
                    landmark = face_landmarks.landmark[index]
                    h, w, _ = image.shape
                    x, y = int(landmark.x * w), int(landmark.y * h)
                    cv2.circle(image, (x, y), 2, (255, 0, 0), -1)

                for index in eyebrow_indices:
                    landmark = face_landmarks.landmark[index]
                    h, w, _ = image.shape
                    x, y = int(landmark.x * w), int(landmark.y * h)
                    cv2.circle(image, (x, y), 2, (0, 0, 255), -1)

    # Save the image with landmarks
    cv2.imwrite(output_path, image)
    # Output the colors as JSON
    print(json.dumps(face_colors))

# Ensure the script gets the image path and output path as arguments
if len(sys.argv) > 2:
    image_path = sys.argv[1]
    output_path = sys.argv[2]
    analyze_face_colors(image_path, output_path)

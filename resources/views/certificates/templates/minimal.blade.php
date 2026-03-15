<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #0f172a;
            width: 297mm;
            height: 210mm;
            position: relative;
            overflow: hidden;
        }

        .certificate-wrapper {
            width: 100%;
            height: 100%;
            position: relative;
            background: #ffffff;
        }

        /* Side accent */
        .side-accent {
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background: {{ $primary_color }};
        }

        .content {
            position: relative;
            z-index: 10;
            padding: 60px 80px 60px 100px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* Logo */
        .logo-section {
            margin-bottom: 30px;
        }
        .logo-section img {
            max-height: 35px;
            max-width: 150px;
        }
        .org-name {
            font-size: 12px;
            color: #94a3b8;
            letter-spacing: 3px;
            text-transform: uppercase;
            font-weight: 500;
        }

        /* Title */
        .certificate-label {
            font-size: 10px;
            color: {{ $primary_color }};
            letter-spacing: 6px;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .certificate-title {
            font-size: 28px;
            font-weight: 200;
            color: #0f172a;
            letter-spacing: 1px;
        }

        /* Thin line */
        .thin-line {
            width: 40px;
            height: 2px;
            background: {{ $primary_color }};
            margin: 20px 0;
        }

        /* Student */
        .student-name {
            font-size: 36px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 15px;
            letter-spacing: -0.5px;
        }

        /* Course info */
        .course-description {
            font-size: 14px;
            color: #475569;
            line-height: 1.8;
            max-width: 550px;
        }
        .course-name {
            font-weight: 700;
            color: #0f172a;
        }

        /* Meta info */
        .meta-grid {
            display: flex;
            gap: 50px;
            margin-top: 25px;
        }
        .meta-item {
            border-left: 2px solid {{ $primary_color }}33;
            padding-left: 12px;
        }
        .meta-label {
            font-size: 9px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 3px;
        }
        .meta-value {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }

        /* Footer */
        .footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 35px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }

        .signature-block {
            text-align: left;
        }
        .signature-img {
            max-height: 35px;
            margin-bottom: 2px;
        }
        .signature-name {
            font-size: 12px;
            font-weight: 700;
            color: #334155;
        }
        .signature-role {
            font-size: 9px;
            color: #94a3b8;
        }

        .footer-right {
            display: flex;
            align-items: flex-end;
            gap: 30px;
        }

        .qr-section {
            text-align: center;
        }
        .qr-section img {
            width: 55px;
            height: 55px;
        }

        .cert-number {
            font-size: 9px;
            color: #94a3b8;
            font-family: 'Courier New', monospace;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 80px;
            font-weight: 900;
            color: rgba(0,0,0,0.03);
            letter-spacing: 15px;
            text-transform: uppercase;
            z-index: 5;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="certificate-wrapper">
        <div class="side-accent"></div>

        @if($is_preview)
            <div class="watermark">PREVIEW</div>
        @endif

        <div class="content">
            <!-- Logo -->
            <div class="logo-section">
                @if($logo_url)
                    <img src="{{ $logo_url }}" alt="Logo">
                @else
                    <div class="org-name">{{ $organization_name }}</div>
                @endif
            </div>

            <!-- Title -->
            <div class="certificate-label">Certificate</div>
            <div class="certificate-title">of Completion</div>

            <div class="thin-line"></div>

            <!-- Student -->
            <div class="student-name">{{ $student_name }}</div>

            <!-- Description -->
            <div class="course-description">
                Has successfully completed the course
                <span class="course-name">"{{ $course_name }}"</span>
                @if($instructor_name && $instructor_name !== 'N/A')
                    taught by {{ $instructor_name }}
                @endif
                on {{ $issue_date }}.
            </div>

            <!-- Meta info -->
            <div class="meta-grid">
                @if($final_grade)
                    <div class="meta-item">
                        <div class="meta-label">Grade</div>
                        <div class="meta-value">{{ $final_grade }}</div>
                    </div>
                @endif
                @if($total_hours > 0)
                    <div class="meta-item">
                        <div class="meta-label">Duration</div>
                        <div class="meta-value">{{ $total_hours }} hours</div>
                    </div>
                @endif
                @if($skill_level && $skill_level !== 'N/A')
                    <div class="meta-item">
                        <div class="meta-label">Level</div>
                        <div class="meta-value">{{ $skill_level }}</div>
                    </div>
                @endif
                <div class="meta-item">
                    <div class="meta-label">Date</div>
                    <div class="meta-value">{{ $issue_date }}</div>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                <div class="signature-block">
                    @if($instructor_signature_url)
                        <img src="{{ $instructor_signature_url }}" class="signature-img" alt="Signature">
                    @endif
                    <div class="signature-name">{{ $instructor_name }}</div>
                    <div class="signature-role">Course Instructor</div>
                </div>

                <div class="footer-right">
                    @if($qr_code_url)
                        <div class="qr-section">
                            <img src="{{ $qr_code_url }}" alt="QR">
                        </div>
                    @endif
                    <div>
                        <div class="cert-number">{{ $certificate_id }}</div>
                        @if($expiry_date)
                            <div class="cert-number">Valid until {{ $expiry_date }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

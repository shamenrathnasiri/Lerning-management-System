<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            width: 297mm;
            height: 210mm;
            position: relative;
            overflow: hidden;
        }

        .certificate-wrapper {
            width: 100%;
            height: 100%;
            position: relative;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #f1f5f9 100%);
        }

        /* Decorative accent bar */
        .accent-bar {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 8px;
            background: linear-gradient(90deg, {{ $primary_color }}, {{ $primary_color }}cc, {{ $primary_color }}66);
        }

        .accent-bar-bottom {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 8px;
            background: linear-gradient(90deg, {{ $primary_color }}66, {{ $primary_color }}cc, {{ $primary_color }});
        }

        /* Decorative circles */
        .circle-decoration {
            position: absolute;
            border-radius: 50%;
            opacity: 0.06;
            background: {{ $primary_color }};
        }
        .circle-1 { width: 300px; height: 300px; top: -100px; right: -80px; }
        .circle-2 { width: 200px; height: 200px; bottom: -60px; left: -50px; }
        .circle-3 { width: 150px; height: 150px; top: 50%; left: 60%; }

        /* Content area */
        .content {
            position: relative;
            z-index: 10;
            padding: 40px 60px;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        /* Logo */
        .logo-section {
            margin-bottom: 15px;
        }
        .logo-section img {
            max-height: 50px;
            max-width: 180px;
        }
        .org-name {
            font-size: 11px;
            color: #64748b;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-top: 5px;
        }

        /* Title */
        .certificate-label {
            font-size: 11px;
            color: {{ $primary_color }};
            letter-spacing: 5px;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .certificate-title {
            font-size: 36px;
            font-weight: 300;
            color: #0f172a;
            letter-spacing: 2px;
            margin-bottom: 8px;
        }
        .certificate-title span {
            color: {{ $primary_color }};
            font-weight: 600;
        }

        /* Divider */
        .divider {
            width: 80px;
            height: 3px;
            background: {{ $primary_color }};
            margin: 10px auto;
            border-radius: 2px;
        }

        /* Presented to */
        .presented-to {
            font-size: 12px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-top: 10px;
        }

        /* Student name */
        .student-name {
            font-size: 32px;
            font-weight: 700;
            color: #0f172a;
            margin: 8px 0;
            font-style: italic;
        }

        /* Course info */
        .course-info {
            font-size: 13px;
            color: #475569;
            max-width: 600px;
            line-height: 1.6;
            margin-top: 5px;
        }
        .course-name {
            font-weight: 700;
            color: {{ $primary_color }};
            font-size: 15px;
        }

        /* Achievement badges */
        .badges {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 15px;
        }
        .badge-item {
            text-align: center;
        }
        .badge-value {
            font-size: 18px;
            font-weight: 700;
            color: {{ $primary_color }};
        }
        .badge-label {
            font-size: 9px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Bottom section */
        .bottom-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            width: 100%;
            margin-top: 20px;
            padding: 0 20px;
        }

        .signature-block {
            text-align: center;
            width: 180px;
        }
        .signature-line {
            border-top: 1px solid #cbd5e1;
            margin-bottom: 5px;
            padding-top: 5px;
        }
        .signature-name {
            font-size: 12px;
            font-weight: 600;
            color: #334155;
        }
        .signature-role {
            font-size: 9px;
            color: #94a3b8;
        }
        .signature-img {
            max-height: 40px;
            margin-bottom: 5px;
        }

        .qr-section {
            text-align: center;
        }
        .qr-section img {
            width: 70px;
            height: 70px;
        }
        .cert-number {
            font-size: 8px;
            color: #94a3b8;
            margin-top: 3px;
            font-family: monospace;
        }

        .date-block {
            text-align: center;
            width: 180px;
        }
        .date-value {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }
        .date-label {
            font-size: 9px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Watermark for preview */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 80px;
            font-weight: 900;
            color: rgba(0,0,0,0.04);
            letter-spacing: 15px;
            text-transform: uppercase;
            z-index: 5;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="certificate-wrapper">
        <!-- Decorative elements -->
        <div class="accent-bar"></div>
        <div class="accent-bar-bottom"></div>
        <div class="circle-decoration circle-1"></div>
        <div class="circle-decoration circle-2"></div>
        <div class="circle-decoration circle-3"></div>

        @if($is_preview)
            <div class="watermark">PREVIEW</div>
        @endif

        <div class="content">
            <!-- Logo -->
            <div class="logo-section">
                @if($logo_url)
                    <img src="{{ $logo_url }}" alt="Logo">
                @endif
                <div class="org-name">{{ $organization_name }}</div>
            </div>

            <!-- Title -->
            <div class="certificate-label">Certificate</div>
            <div class="certificate-title">OF <span>COMPLETION</span></div>

            <div class="divider"></div>

            <!-- Student -->
            <div class="presented-to">This is presented to</div>
            <div class="student-name">{{ $student_name }}</div>

            <!-- Course info -->
            <div class="course-info">
                For successfully completing the course
                <br>
                <span class="course-name">{{ $course_name }}</span>
                <br>
                @if($course_level && $course_level !== 'N/A')
                    Level: {{ $course_level }}
                @endif
            </div>

            <!-- Badges -->
            <div class="badges">
                @if($final_grade)
                    <div class="badge-item">
                        <div class="badge-value">{{ $final_grade }}</div>
                        <div class="badge-label">Final Grade</div>
                    </div>
                @endif
                @if($total_hours > 0)
                    <div class="badge-item">
                        <div class="badge-value">{{ $total_hours }}h</div>
                        <div class="badge-label">Total Hours</div>
                    </div>
                @endif
                @if($skill_level && $skill_level !== 'N/A')
                    <div class="badge-item">
                        <div class="badge-value">{{ $skill_level }}</div>
                        <div class="badge-label">Skill Level</div>
                    </div>
                @endif
            </div>

            <!-- Bottom section -->
            <div class="bottom-section">
                <!-- Instructor signature -->
                <div class="signature-block">
                    @if($instructor_signature_url)
                        <img src="{{ $instructor_signature_url }}" class="signature-img" alt="Signature">
                    @endif
                    <div class="signature-line">
                        <div class="signature-name">{{ $instructor_name }}</div>
                        <div class="signature-role">Course Instructor</div>
                    </div>
                </div>

                <!-- QR code -->
                @if($qr_code_url)
                    <div class="qr-section">
                        <img src="{{ $qr_code_url }}" alt="Verify">
                        <div class="cert-number">{{ $certificate_id }}</div>
                    </div>
                @endif

                <!-- Date -->
                <div class="date-block">
                    <div class="date-value">{{ $issue_date }}</div>
                    <div class="date-label">Date Issued</div>
                    @if($expiry_date)
                        <div class="date-value" style="margin-top: 8px; font-size: 11px;">{{ $expiry_date }}</div>
                        <div class="date-label">Valid Until</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</body>
</html>

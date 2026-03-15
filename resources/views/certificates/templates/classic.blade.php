<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            color: #1a1a2e;
            width: 297mm;
            height: 210mm;
            position: relative;
            overflow: hidden;
        }

        .certificate-wrapper {
            width: 100%;
            height: 100%;
            position: relative;
            background: #fffef7;
        }

        /* Ornate border */
        .border-outer {
            position: absolute;
            top: 12px; left: 12px; right: 12px; bottom: 12px;
            border: 3px solid {{ $primary_color }};
        }
        .border-inner {
            position: absolute;
            top: 20px; left: 20px; right: 20px; bottom: 20px;
            border: 1px solid {{ $primary_color }}88;
        }

        /* Corner ornaments */
        .corner {
            position: absolute;
            width: 60px;
            height: 60px;
            border-color: {{ $primary_color }};
        }
        .corner-tl { top: 25px; left: 25px; border-top: 3px solid; border-left: 3px solid; }
        .corner-tr { top: 25px; right: 25px; border-top: 3px solid; border-right: 3px solid; }
        .corner-bl { bottom: 25px; left: 25px; border-bottom: 3px solid; border-left: 3px solid; }
        .corner-br { bottom: 25px; right: 25px; border-bottom: 3px solid; border-right: 3px solid; }

        /* Gold ribbon accent */
        .ribbon-top {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 200px;
            height: 4px;
            background: linear-gradient(90deg, transparent, {{ $primary_color }}, transparent);
        }

        .content {
            position: relative;
            z-index: 10;
            padding: 50px 80px;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        /* Logo */
        .logo-section {
            margin-bottom: 8px;
        }
        .logo-section img {
            max-height: 45px;
            max-width: 160px;
        }
        .org-name {
            font-size: 14px;
            color: #64748b;
            letter-spacing: 4px;
            text-transform: uppercase;
        }

        /* Seal decoration */
        .seal {
            width: 70px;
            height: 70px;
            border: 2px solid {{ $primary_color }};
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 10px auto;
        }
        .seal-inner {
            width: 54px;
            height: 54px;
            border: 1px solid {{ $primary_color }}88;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: {{ $primary_color }};
        }

        .certificate-title {
            font-size: 40px;
            font-weight: 400;
            color: {{ $primary_color }};
            letter-spacing: 6px;
            text-transform: uppercase;
            margin-bottom: 3px;
            font-style: italic;
        }

        .certificate-subtitle {
            font-size: 16px;
            color: #64748b;
            letter-spacing: 8px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        /* Ornate divider */
        .ornate-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 8px 0;
        }
        .ornate-line {
            width: 80px;
            height: 1px;
            background: {{ $primary_color }}88;
        }
        .ornate-diamond {
            width: 8px;
            height: 8px;
            background: {{ $primary_color }};
            transform: rotate(45deg);
        }

        .presented-to {
            font-size: 13px;
            color: #94a3b8;
            font-style: italic;
            margin-top: 8px;
        }

        .student-name {
            font-size: 34px;
            font-weight: 400;
            color: #0f172a;
            font-style: italic;
            margin: 5px 0;
            border-bottom: 1px solid {{ $primary_color }}44;
            padding-bottom: 5px;
        }

        .course-info {
            font-size: 13px;
            color: #475569;
            line-height: 1.7;
            max-width: 600px;
            margin-top: 5px;
        }
        .course-name {
            font-weight: 700;
            color: {{ $primary_color }};
            font-size: 16px;
            font-style: italic;
        }

        .details-row {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 10px;
            font-size: 12px;
            color: #64748b;
        }
        .detail-item strong {
            color: #334155;
        }

        .bottom-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            width: 100%;
            margin-top: 20px;
            padding: 0 30px;
        }

        .signature-block {
            text-align: center;
            width: 180px;
        }
        .signature-line {
            border-top: 1px solid #94a3b8;
            margin-bottom: 4px;
            padding-top: 5px;
        }
        .signature-name {
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            font-style: italic;
        }
        .signature-role {
            font-size: 9px;
            color: #94a3b8;
        }
        .signature-img {
            max-height: 40px;
            margin-bottom: 4px;
        }

        .qr-section {
            text-align: center;
        }
        .qr-section img {
            width: 65px;
            height: 65px;
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
            font-weight: 700;
            color: #334155;
            font-style: italic;
        }
        .date-label {
            font-size: 9px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

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
        <div class="border-outer"></div>
        <div class="border-inner"></div>
        <div class="corner corner-tl"></div>
        <div class="corner corner-tr"></div>
        <div class="corner corner-bl"></div>
        <div class="corner corner-br"></div>
        <div class="ribbon-top"></div>

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

            <!-- Seal -->
            <div class="seal">
                <div class="seal-inner">★</div>
            </div>

            <div class="certificate-title">Certificate</div>
            <div class="certificate-subtitle">of Completion</div>

            <div class="ornate-divider">
                <div class="ornate-line"></div>
                <div class="ornate-diamond"></div>
                <div class="ornate-line"></div>
            </div>

            <div class="presented-to">This certificate is proudly presented to</div>
            <div class="student-name">{{ $student_name }}</div>

            <div class="course-info">
                In recognition of the successful completion of
                <br>
                <span class="course-name">"{{ $course_name }}"</span>
            </div>

            <div class="details-row">
                @if($final_grade)
                    <div class="detail-item">Grade: <strong>{{ $final_grade }}</strong></div>
                @endif
                @if($total_hours > 0)
                    <div class="detail-item">Duration: <strong>{{ $total_hours }} hours</strong></div>
                @endif
                @if($skill_level && $skill_level !== 'N/A')
                    <div class="detail-item">Level: <strong>{{ $skill_level }}</strong></div>
                @endif
            </div>

            <!-- Bottom -->
            <div class="bottom-section">
                <div class="signature-block">
                    @if($instructor_signature_url)
                        <img src="{{ $instructor_signature_url }}" class="signature-img" alt="Signature">
                    @endif
                    <div class="signature-line">
                        <div class="signature-name">{{ $instructor_name }}</div>
                        <div class="signature-role">Course Instructor</div>
                    </div>
                </div>

                @if($qr_code_url)
                    <div class="qr-section">
                        <img src="{{ $qr_code_url }}" alt="QR Code">
                        <div class="cert-number">{{ $certificate_id }}</div>
                    </div>
                @endif

                <div class="date-block">
                    <div class="date-value">{{ $issue_date }}</div>
                    <div class="date-label">Date of Completion</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

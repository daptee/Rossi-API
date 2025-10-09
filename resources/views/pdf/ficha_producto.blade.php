<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 30px;
            color: #000;
        }

        .header {
            width: 100%;
            border-bottom: 1px solid #000;
            margin-bottom: 10px;
        }

        .title {
            font-size: 22px;
            font-weight: bold;
        }

        .logo {
            height: 35px;
            margin-bottom: 10px;
        }

        .product-image {
            text-align: center;
            margin-top: 25px;
        }

        .product-image img {
            width: 300px;
        }

        .section-title {
            font-weight: bold;
            border-bottom: 1px solid #000;
            margin-top: 25px;
            padding-bottom: 3px;
            text-transform: uppercase;
        }

        .values {
            margin-top: 5px;
            font-size: 13px;
        }

        .values span {
            margin-right: 10px;
        }

        .footer {
            position: fixed;
            bottom: 10px;   /* distancia desde el borde inferior */
            left: 0;
            right: 0;
            text-align: center;
            font-size: 11px;
        }
    </style>
</head>

<body>
    <div class="header">
        <table width="100%">
            <tr>
                <td style="text-align: left;">
                    <div class="title">{{ strtoupper($data['product_title']) }}</div>
                </td>
                <td style="text-align: right;">
                    <img class="logo" src="{{ public_path('logo/rossi-logo.svg') }}" alt="Logo">
                </td>
            </tr>
        </table>
    </div>


    @if($data['image'])
        <div class="product-image">
            <img src="{{ $data['image'] }}" alt="Producto">
        </div>
    @endif

    @foreach($data['model'] as $section)
        <div class="section">
            <div class="section-title">{{ strtoupper($section['title']) }}</div>
            <div class="values">
                @foreach($section['values'] as $value)
                    <span>{{ strtoupper($value) }}</span>
                    @if(!$loop->last)
                        <span>|</span>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="footer">
        www.rossiequipamientos.com.ar
    </div>
</body>

</html>
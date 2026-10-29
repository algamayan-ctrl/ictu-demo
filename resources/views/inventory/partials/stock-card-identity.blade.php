<table class="sao-stock-card-identity" aria-label="Stock card item information">
    <colgroup><col style="width:74%"><col style="width:26%"></colgroup>
    <tbody>
        <tr><td><strong>Entity Name:</strong> CAMARINES SUR POLYTECHNIC COLLEGES–NABUA, CAM. SUR</td><td><strong>Fund Cluster:</strong> <span data-stock-field="fund-cluster"></span></td></tr>
        <tr><td><strong>Item:</strong> {{ $item->unique_description }}</td><td><strong>Stock No.:</strong> <span data-stock-field="stock-number"></span></td></tr>
        <tr><td><strong>Description:</strong> {{ $item->specification }}</td><td><strong>Re-order Point:</strong> <span data-stock-field="reorder-point"></span></td></tr>
        <tr><td><strong>Unit of Measurement:</strong> {{ $item->unit->unit_name }}</td><td></td></tr>
    </tbody>
</table>

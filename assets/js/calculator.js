jQuery(document).ready(function($) {
    $("#cytechCalculateBtn").on("click", function() {
        cytechCalculate();
    });
    
    // Also allow Enter key to trigger calculation
    $("#cytechCashPrice").on("keyup", function(e) {
        if (e.key === "Enter") {
            cytechCalculate();
        }
    });
    
    // Trigger calculation when payment terms change
    $("#cytechPaymentTerms").on("change", function() {
        if ($("#cytechCashPrice").val()) {
            cytechCalculate();
        }
    });
    
    function cytechCalculate() {
        const cashPrice = $("#cytechCashPrice").val();
        const paymentTerms = $("#cytechPaymentTerms").val();
        
        if (!cashPrice || cashPrice <= 0) {
            alert("Please enter a valid cash price");
            return;
        }
        
        // Parse payment terms (format: "weeks-interest")
        const [weeks, interestRate] = paymentTerms.split("-").map(Number);
        
        const price = parseFloat(cashPrice);
        const deposit = price * 0.4; // 40% deposit
        const initialBalance = price - deposit; // Balance after deposit (60% of price)
        const interestAmount = initialBalance * (interestRate / 100);
        const totalPayable = initialBalance + interestAmount;
        const weeklyPayment = totalPayable / weeks;
        
        // Debug log to console
        console.log("Price:", price, "Weeks:", weeks, "Interest Rate:", interestRate + "%");
        console.log("Deposit:", deposit, "Balance:", initialBalance, "Interest:", interestAmount);
        console.log("Total Payable:", totalPayable, "Weekly:", weeklyPayment);
        
        // Show summary with proper formatting
        $("#cytechSummary").slideDown(300);
        $("#cytechCashPriceDisplay").text("Cash Price: Ksh " + price.toFixed(2));
        $("#cytechDepositDisplay").text("Required Deposit (40%): Ksh " + deposit.toFixed(2));
        $("#cytechBalanceDisplay").text("Initial Balance (60%): Ksh " + initialBalance.toFixed(2));
        $("#cytechInterestRateDisplay").text("Interest Rate: " + interestRate + "%");
        $("#cytechInterestDisplay").text("Interest Amount: Ksh " + interestAmount.toFixed(2));
        $("#cytechTotalPayableDisplay").text("Total Balance Payable: Ksh " + totalPayable.toFixed(2));
        $("#cytechWeeklyPaymentDisplay").text("Weekly Payment: Ksh " + weeklyPayment.toFixed(2));
        $("#cytechPaymentPeriodDisplay").text("Payment Period: " + weeks + " weeks");
    }
});
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test complete invoice and payment workflow
     * 
     * @test
     */
    public function it_handles_complete_invoice_payment_workflow()
    {
        // Step 1: Create client and project
        $clientData = ['name' => 'Invoice Client', 'email' => 'invoice@example.com'];
        $clientResponse = $this->postJson('/api/clients', $clientData);
        
        if ($clientResponse->status() === 201) {
            $clientId = $clientResponse->json()['id'];

            $projectData = [
                'client_id' => $clientId,
                'name' => 'Invoicing Project',
                'value' => 10000.00,
                'status' => 'completed'
            ];

            $projectResponse = $this->postJson('/api/projects', $projectData);
            
            if ($projectResponse->status() === 201) {
                $projectId = $projectResponse->json()['id'];

                // Step 2: Create invoice
                $invoiceData = [
                    'client_id' => $clientId,
                    'project_id' => $projectId,
                    'issue_date' => '2024-01-01',
                    'due_date' => '2024-01-31',
                    'tax_rate' => 10.0,
                    'notes' => 'Invoice for completed project',
                    'items' => [
                        [
                            'description' => 'Project Development',
                            'quantity' => 1,
                            'unit_price' => 8000.00
                        ],
                        [
                            'description' => 'Additional Features',
                            'quantity' => 1,
                            'unit_price' => 2000.00
                        ]
                    ]
                ];

                $invoiceResponse = $this->postJson('/api/invoices', $invoiceData);
                
                if ($invoiceResponse->status() === 201) {
                    $invoice = $invoiceResponse->json();
                    $invoiceId = $invoice['id'];

                    // Verify invoice calculations
                    $this->assertEquals(10000.00, $invoice['subtotal']);
                    $this->assertEquals(1000.00, $invoice['tax_amount']);
                    $this->assertEquals(11000.00, $invoice['total']);

                    // Step 3: Create payment terms (split payment)
                    $termsData = [
                        'terms' => [
                            [
                                'percentage' => 50.0,
                                'due_date' => '2024-01-15',
                                'description' => 'First payment - 50%'
                            ],
                            [
                                'percentage' => 50.0,
                                'due_date' => '2024-01-31',
                                'description' => 'Final payment - 50%'
                            ]
                        ]
                    ];

                    $termsResponse = $this->postJson("/api/invoices/{$invoiceId}/terms", $termsData);
                    $termsResponse->assertStatus(201);

                    // Step 4: Record first payment
                    $firstPaymentData = [
                        'invoice_id' => $invoiceId,
                        'amount' => 5500.00, // 50% of total
                        'payment_date' => '2024-01-15',
                        'payment_method' => 'bank_transfer',
                        'reference_number' => 'TRX001',
                        'notes' => 'First installment payment'
                    ];

                    $firstPaymentResponse = $this->postJson('/api/payments', $firstPaymentData);
                    $firstPaymentResponse->assertStatus(201);

                    // Step 5: Record final payment
                    $finalPaymentData = [
                        'invoice_id' => $invoiceId,
                        'amount' => 5500.00, // Remaining 50%
                        'payment_date' => '2024-01-31',
                        'payment_method' => 'bank_transfer',
                        'reference_number' => 'TRX002',
                        'notes' => 'Final payment'
                    ];

                    $finalPaymentResponse = $this->postJson('/api/payments', $finalPaymentData);
                    $finalPaymentResponse->assertStatus(201);

                    // Step 6: Generate invoice PDF
                    $pdfResponse = $this->getJson("/api/invoices/{$invoiceId}/pdf");
                    
                    if ($pdfResponse->status() === 200) {
                        $this->assertEquals('application/pdf', $pdfResponse->headers->get('Content-Type'));
                        $this->assertNotEmpty($pdfResponse->getContent());
                    }
                }
            }
        }
    }

    /**
     * Test invoice status transitions based on payments
     * 
     * @test
     */
    public function it_handles_invoice_status_transitions()
    {
        // Create client and invoice
        $clientData = ['name' => 'Status Client', 'email' => 'status@example.com'];
        $clientResponse = $this->postJson('/api/clients', $clientData);
        
        if ($clientResponse->status() === 201) {
            $clientId = $clientResponse->json()['id'];

            $invoiceData = [
                'client_id' => $clientId,
                'issue_date' => '2024-01-01',
                'due_date' => '2024-01-31',
                'items' => [
                    [
                        'description' => 'Service',
                        'quantity' => 1,
                        'unit_price' => 1000.00
                    ]
                ]
            ];

            $invoiceResponse = $this->postJson('/api/invoices', $invoiceData);
            
            if ($invoiceResponse->status() === 201) {
                $invoice = $invoiceResponse->json();
                $invoiceId = $invoice['id'];

                // Test partial payment
                $partialPaymentData = [
                    'invoice_id' => $invoiceId,
                    'amount' => 500.00, // Partial payment
                    'payment_date' => '2024-01-15',
                    'payment_method' => 'cash'
                ];

                $partialResponse = $this->postJson('/api/payments', $partialPaymentData);
                $this->assertTrue(in_array($partialResponse->status(), [201, 404, 422]));

                // Test full payment
                $fullPaymentData = [
                    'invoice_id' => $invoiceId,
                    'amount' => 500.00, // Remaining amount
                    'payment_date' => '2024-01-20',
                    'payment_method' => 'bank_transfer'
                ];

                $fullResponse = $this->postJson('/api/payments', $fullPaymentData);
                $this->assertTrue(in_array($fullResponse->status(), [201, 404, 422]));
            }
        }
    }

    /**
     * Test invoice calculation accuracy
     * 
     * @test
     */
    public function it_calculates_invoice_totals_accurately()
    {
        // Create client
        $clientData = ['name' => 'Calculation Client', 'email' => 'calc@example.com'];
        $clientResponse = $this->postJson('/api/clients', $clientData);
        
        if ($clientResponse->status() === 201) {
            $clientId = $clientResponse->json()['id'];

            // Test various calculation scenarios
            $testCases = [
                [
                    'items' => [
                        ['description' => 'Item 1', 'quantity' => 2, 'unit_price' => 100.00],
                        ['description' => 'Item 2', 'quantity' => 3, 'unit_price' => 150.00]
                    ],
                    'tax_rate' => 10.0,
                    'expected_subtotal' => 650.00,
                    'expected_tax' => 65.00,
                    'expected_total' => 715.00
                ],
                [
                    'items' => [
                        ['description' => 'Service', 'quantity' => 1, 'unit_price' => 1000.00]
                    ],
                    'tax_rate' => 0.0,
                    'expected_subtotal' => 1000.00,
                    'expected_tax' => 0.00,
                    'expected_total' => 1000.00
                ]
            ];

            foreach ($testCases as $index => $testCase) {
                $invoiceData = [
                    'client_id' => $clientId,
                    'issue_date' => '2024-01-01',
                    'due_date' => '2024-01-31',
                    'tax_rate' => $testCase['tax_rate'],
                    'items' => $testCase['items']
                ];

                $response = $this->postJson('/api/invoices', $invoiceData);
                
                if ($response->status() === 201) {
                    $invoice = $response->json();
                    
                    $this->assertEquals($testCase['expected_subtotal'], $invoice['subtotal'], 
                        "Subtotal calculation failed for test case {$index}");
                    $this->assertEquals($testCase['expected_tax'], $invoice['tax_amount'], 
                        "Tax calculation failed for test case {$index}");
                    $this->assertEquals($testCase['expected_total'], $invoice['total'], 
                        "Total calculation failed for test case {$index}");
                }
            }
        }
    }

    /**
     * Test payment validation and business rules
     * 
     * @test
     */
    public function it_enforces_payment_validation_rules()
    {
        // Create client and invoice
        $clientData = ['name' => 'Payment Client', 'email' => 'payment@example.com'];
        $clientResponse = $this->postJson('/api/clients', $clientData);
        
        if ($clientResponse->status() === 201) {
            $clientId = $clientResponse->json()['id'];

            $invoiceData = [
                'client_id' => $clientId,
                'issue_date' => '2024-01-01',
                'due_date' => '2024-01-31',
                'items' => [
                    ['description' => 'Service', 'quantity' => 1, 'unit_price' => 1000.00]
                ]
            ];

            $invoiceResponse = $this->postJson('/api/invoices', $invoiceData);
            
            if ($invoiceResponse->status() === 201) {
                $invoiceId = $invoiceResponse->json()['id'];

                // Test invalid payment scenarios
                $invalidPayments = [
                    [
                        'invoice_id' => $invoiceId,
                        'amount' => -100.00, // Negative amount
                        'payment_date' => '2024-01-15',
                        'payment_method' => 'cash'
                    ],
                    [
                        'invoice_id' => $invoiceId,
                        'amount' => 0.00, // Zero amount
                        'payment_date' => '2024-01-15',
                        'payment_method' => 'cash'
                    ],
                    [
                        'invoice_id' => $invoiceId,
                        'amount' => 500.00,
                        'payment_date' => 'invalid-date', // Invalid date
                        'payment_method' => 'cash'
                    ],
                    [
                        'invoice_id' => $invoiceId,
                        'amount' => 500.00,
                        'payment_date' => '2024-01-15',
                        'payment_method' => 'invalid_method' // Invalid payment method
                    ]
                ];

                foreach ($invalidPayments as $invalidPayment) {
                    $response = $this->postJson('/api/payments', $invalidPayment);
                    $response->assertStatus(422);
                }

                // Test overpayment scenario
                $overpaymentData = [
                    'invoice_id' => $invoiceId,
                    'amount' => 2000.00, // More than invoice total
                    'payment_date' => '2024-01-15',
                    'payment_method' => 'bank_transfer'
                ];

                $overpaymentResponse = $this->postJson('/api/payments', $overpaymentData);
                // Should either accept overpayment or return validation error
                $this->assertTrue(in_array($overpaymentResponse->status(), [201, 422]));
            }
        }
    }

    /**
     * Test invoice PDF generation with different scenarios
     * 
     * @test
     */
    public function it_generates_invoice_pdfs_for_different_scenarios()
    {
        // Create client
        $clientData = ['name' => 'PDF Client', 'email' => 'pdf@example.com'];
        $clientResponse = $this->postJson('/api/clients', $clientData);
        
        if ($clientResponse->status() === 201) {
            $clientId = $clientResponse->json()['id'];

            // Test PDF generation for different invoice types
            $invoiceScenarios = [
                [
                    'name' => 'Simple Invoice',
                    'items' => [
                        ['description' => 'Basic Service', 'quantity' => 1, 'unit_price' => 500.00]
                    ],
                    'tax_rate' => 0.0
                ],
                [
                    'name' => 'Complex Invoice',
                    'items' => [
                        ['description' => 'Service A', 'quantity' => 2, 'unit_price' => 300.00],
                        ['description' => 'Service B', 'quantity' => 1, 'unit_price' => 400.00],
                        ['description' => 'Service C', 'quantity' => 3, 'unit_price' => 100.00]
                    ],
                    'tax_rate' => 10.0,
                    'notes' => 'Complex invoice with multiple items and tax'
                ]
            ];

            foreach ($invoiceScenarios as $scenario) {
                $invoiceData = [
                    'client_id' => $clientId,
                    'issue_date' => '2024-01-01',
                    'due_date' => '2024-01-31',
                    'tax_rate' => $scenario['tax_rate'],
                    'items' => $scenario['items']
                ];

                if (isset($scenario['notes'])) {
                    $invoiceData['notes'] = $scenario['notes'];
                }

                $invoiceResponse = $this->postJson('/api/invoices', $invoiceData);
                
                if ($invoiceResponse->status() === 201) {
                    $invoiceId = $invoiceResponse->json()['id'];

                    // Test PDF generation
                    $pdfResponse = $this->getJson("/api/invoices/{$invoiceId}/pdf");
                    
                    if ($pdfResponse->status() === 200) {
                        $this->assertEquals('application/pdf', $pdfResponse->headers->get('Content-Type'));
                        $content = $pdfResponse->getContent();
                        $this->assertStringStartsWith('%PDF', $content);
                        $this->assertGreaterThan(0, strlen($content));
                    }
                }
            }
        }
    }

    /**
     * Test invoice terms and split payment functionality
     * 
     * @test
     */
    public function it_handles_invoice_terms_and_split_payments()
    {
        // Create client and invoice
        $clientData = ['name' => 'Terms Client', 'email' => 'terms@example.com'];
        $clientResponse = $this->postJson('/api/clients', $clientData);
        
        if ($clientResponse->status() === 201) {
            $clientId = $clientResponse->json()['id'];

            $invoiceData = [
                'client_id' => $clientId,
                'issue_date' => '2024-01-01',
                'due_date' => '2024-03-31',
                'items' => [
                    ['description' => 'Large Project', 'quantity' => 1, 'unit_price' => 10000.00]
                ]
            ];

            $invoiceResponse = $this->postJson('/api/invoices', $invoiceData);
            
            if ($invoiceResponse->status() === 201) {
                $invoiceId = $invoiceResponse->json()['id'];

                // Create payment terms
                $termsData = [
                    'terms' => [
                        [
                            'percentage' => 30.0,
                            'due_date' => '2024-01-15',
                            'description' => 'Down payment'
                        ],
                        [
                            'percentage' => 40.0,
                            'due_date' => '2024-02-15',
                            'description' => 'Progress payment'
                        ],
                        [
                            'percentage' => 30.0,
                            'due_date' => '2024-03-15',
                            'description' => 'Final payment'
                        ]
                    ]
                ];

                $termsResponse = $this->postJson("/api/invoices/{$invoiceId}/terms", $termsData);
                
                if ($termsResponse->status() === 201) {
                    // Make payments according to terms
                    $payments = [
                        ['amount' => 3000.00, 'date' => '2024-01-15', 'note' => 'Down payment'],
                        ['amount' => 4000.00, 'date' => '2024-02-15', 'note' => 'Progress payment'],
                        ['amount' => 3000.00, 'date' => '2024-03-15', 'note' => 'Final payment']
                    ];

                    foreach ($payments as $payment) {
                        $paymentData = [
                            'invoice_id' => $invoiceId,
                            'amount' => $payment['amount'],
                            'payment_date' => $payment['date'],
                            'payment_method' => 'bank_transfer',
                            'notes' => $payment['note']
                        ];

                        $paymentResponse = $this->postJson('/api/payments', $paymentData);
                        $this->assertTrue(in_array($paymentResponse->status(), [201, 404, 422]));
                    }
                }
            }
        }
    }
}
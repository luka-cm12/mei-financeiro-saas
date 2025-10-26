<?php

require_once __DIR__ . '/AuthMiddleware.php';
require_once __DIR__ . '/../models/FeatureUsage.php';

class FeatureMiddleware {
    private $authMiddleware;
    private $conn;

    public function __construct($db) {
        $this->authMiddleware = new AuthMiddleware();
        $this->conn = $db;
    }

    /**
     * Verificar se usuário pode usar uma feature antes de executar ação
     */
    public function checkFeatureAccess($featureName, $amount = 1) {
        // Primeiro verificar autenticação
        $authResult = $this->authMiddleware->authenticate();
        
        if (!$authResult['success']) {
            return $authResult;
        }

        $userId = $authResult['user_id'];
        
        try {
            $featureUsage = new FeatureUsage($this->conn);
            
            if (!$featureUsage->canUseFeature($userId, $featureName, $amount)) {
                return [
                    'success' => false,
                    'message' => 'Limite de uso da feature atingido. Faça upgrade do seu plano.',
                    'error_code' => 'FEATURE_LIMIT_EXCEEDED',
                    'feature' => $featureName,
                    'current_usage' => $featureUsage->getCurrentUsage($userId, $featureName),
                    'remaining_limit' => $featureUsage->getRemainingLimit($userId, $featureName)
                ];
            }

            return [
                'success' => true,
                'user_id' => $userId,
                'feature_usage' => $featureUsage
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao verificar acesso à feature: ' . $e->getMessage(),
                'error_code' => 'FEATURE_CHECK_ERROR'
            ];
        }
    }

    /**
     * Usar feature e incrementar contador automaticamente
     */
    public function useFeature($featureName, $amount = 1) {
        $checkResult = $this->checkFeatureAccess($featureName, $amount);
        
        if (!$checkResult['success']) {
            return $checkResult;
        }

        $userId = $checkResult['user_id'];
        $featureUsage = $checkResult['feature_usage'];

        try {
            if ($featureUsage->useFeature($userId, $featureName, $amount)) {
                return [
                    'success' => true,
                    'user_id' => $userId,
                    'message' => 'Feature utilizada com sucesso'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Erro ao registrar uso da feature',
                    'error_code' => 'FEATURE_USAGE_ERROR'
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao usar feature: ' . $e->getMessage(),
                'error_code' => 'FEATURE_USAGE_EXCEPTION'
            ];
        }
    }

    /**
     * Verificar se usuário tem acesso a features premium
     */
    public function requiresPremium($userId = null) {
        if (!$userId) {
            $authResult = $this->authMiddleware->authenticate();
            
            if (!$authResult['success']) {
                return $authResult;
            }
            
            $userId = $authResult['user_id'];
        }

        try {
            $userSubscription = new UserSubscription($this->conn);
            $hasActiveSubscription = $userSubscription->hasActiveSubscription($userId);
            
            if (!$hasActiveSubscription) {
                return [
                    'success' => false,
                    'message' => 'Esta funcionalidade requer uma assinatura Premium.',
                    'error_code' => 'PREMIUM_REQUIRED',
                    'upgrade_required' => true
                ];
            }

            return [
                'success' => true,
                'user_id' => $userId
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao verificar assinatura: ' . $e->getMessage(),
                'error_code' => 'SUBSCRIPTION_CHECK_ERROR'
            ];
        }
    }

    /**
     * Enviar resposta de erro padronizada para limite de feature
     */
    public static function sendFeatureLimitResponse($checkResult) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => $checkResult['message'],
            'error_code' => $checkResult['error_code'],
            'data' => [
                'feature' => $checkResult['feature'] ?? null,
                'current_usage' => $checkResult['current_usage'] ?? null,
                'remaining_limit' => $checkResult['remaining_limit'] ?? null,
                'upgrade_required' => $checkResult['upgrade_required'] ?? false
            ]
        ]);
    }
}
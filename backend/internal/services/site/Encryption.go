// Package site - AES-256-GCM encryption for application passwords
// Uses shared crypto package for encryption/decryption
package site

import (
	"wp-plugin-publish/internal/crypto"
	"wp-plugin-publish/pkg/apperror"
)

// encrypt encrypts plaintext using AES-256-GCM (wrapper for crypto package)
func encrypt(plaintext, key []byte) ([]byte, *apperror.AppError) {
	return crypto.Encrypt(plaintext, key)
}

// decrypt decrypts ciphertext using AES-256-GCM (wrapper for crypto package)
func decrypt(ciphertext, key []byte) ([]byte, *apperror.AppError) {
	return crypto.Decrypt(ciphertext, key)
}

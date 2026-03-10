import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';

export interface User {
  id: number;
  email: string;
  username: string;
  displayName: string;
  firstName: string;
  lastName: string;
  avatar: string;
  registeredVia: string;
  roles: string[];
}

interface AuthContextType {
  user: User | null;
  isLoggedIn: boolean;
  isLoading: boolean;
  login: (user: User) => void;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

interface AuthProviderProps {
  children: ReactNode;
  apiUrl: string;
  initialUser?: User | null;
}

export const AuthProvider: React.FC<AuthProviderProps> = ({ 
  children, 
  apiUrl, 
  initialUser = null 
}) => {
  const [user, setUser] = useState<User | null>(initialUser);
  const [isLoading, setIsLoading] = useState(!initialUser);

  useEffect(() => {
    if (!initialUser) {
      refreshUser();
    }
  }, []);

  const refreshUser = async () => {
    setIsLoading(true);
    try {
      const response = await fetch(`${apiUrl}/me`, {
        credentials: 'include',
      });
      const data = await response.json();
      
      if (data.success && data.logged_in) {
        setUser(transformUser(data.user));
      } else {
        setUser(null);
      }
    } catch (error) {
      console.error('Failed to fetch user:', error);
      setUser(null);
    } finally {
      setIsLoading(false);
    }
  };

  const login = (userData: User) => {
    setUser(userData);
  };

  const logout = async () => {
    try {
      await fetch(`${apiUrl}/logout`, {
        method: 'POST',
        credentials: 'include',
      });
      setUser(null);
    } catch (error) {
      console.error('Logout failed:', error);
    }
  };

  return (
    <AuthContext.Provider value={{
      user,
      isLoggedIn: !!user,
      isLoading,
      login,
      logout,
      refreshUser,
    }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = (): AuthContextType => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

// Transform API response to User type
export const transformUser = (apiUser: any): User => ({
  id: apiUser.id,
  email: apiUser.email,
  username: apiUser.username,
  displayName: apiUser.display_name,
  firstName: apiUser.first_name,
  lastName: apiUser.last_name,
  avatar: apiUser.avatar,
  registeredVia: apiUser.registered_via,
  roles: apiUser.roles,
});

export default AuthContext;

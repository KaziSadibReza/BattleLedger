import React from "react";

interface SkeletonLoaderProps {
  width?: string;
  height?: string;
  borderRadius?: string;
}

const SkeletonLoader: React.FC<SkeletonLoaderProps> = ({
  width = "100%",
  height = "20px",
  borderRadius = "4px",
}) => {
  return (
    <div
      className="bl-skeleton"
      style={{
        width,
        height,
        borderRadius,
      }}
    />
  );
};

export default SkeletonLoader;
